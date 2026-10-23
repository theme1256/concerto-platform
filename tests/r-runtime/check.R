# Run with the current concerto5 package installed:
# Rscript --vanilla tests/r-runtime/check.R [repository-root]
args = commandArgs(trailingOnly = TRUE)
root = if (length(args)) normalizePath(args[[1]]) else getwd()
rDir = file.path(root, "src/Concerto/TestBundle/Resources/R")
checks = 0L
check = function(condition, message) {
    if (!isTRUE(condition)) stop(message, call. = FALSE)
    checks <<- checks + 1L
    cat("PASS:", message, "\n")
}

source(file.path(root, "app/config/R/.Rprofile_session"))
profileFromJSON = fromJSON
Sys.setenv(
    CONCERTO_R_DB_CONNECTION = '{"driver":"pdo_mysql"}',
    CONCERTO_R_REDIS_CONNECTION = '{"host":"redis-test","port":6379,"password":""}',
    CONCERTO_R_SESSION_STORAGE = "redis",
    CONCERTO_R_SESSION_FILES_EXPIRATION = "7",
    CONCERTO_R_SERVICE_FIFO_PATH = "/tmp/concerto-service/",
    CONCERTO_R_MAX_EXEC_TIME = "30",
    CONCERTO_R_MAX_IDLE_TIME = "60",
    CONCERTO_R_KEEP_ALIVE_TOLERANCE_TIME = "0",
    CONCERTO_R_SESSION_LOG_LEVEL = "0",
    CONCERTO_R_FORCED_GC_INTERVAL = "30"
)

# Evaluate each real runner only through initialization, before it starts I/O.
for (runner in c("forker.R", "standalone.R")) {
    expressions = parse(file.path(rDir, runner))
    initIndex = which(vapply(expressions, function(expr) {
        is.call(expr) && identical(expr[[1]], quote(concerto5:::concerto.init))
    }, logical(1)))
    check(length(initIndex) == 1L, paste(runner, "has one initialization call"))
    for (setting in c("unset", "0", "1")) {
        if (setting == "unset") Sys.unsetenv("CONCERTO_R_CONTENT_CACHE")
        else Sys.setenv(CONCERTO_R_CONTENT_CACHE = setting)
        for (expr in expressions[seq_len(initIndex)]) eval(expr, .GlobalEnv)
        check(identical(concerto$cacheEnabled, setting != "0"),
              paste(runner, "cache setting", setting))
        check(identical(concerto$serviceFifoDir, "/tmp/concerto-service/"),
              paste(runner, "preserves service FIFO"))
        check(is.list(concerto$redisConnectionParams) &&
              identical(concerto$redisConnectionParams$host, "redis-test"),
              paste(runner, "decodes Redis configuration"))
        check(as.numeric(concerto$sessionFilesExpiration) == 7,
              paste(runner, "preserves session expiration"))
        check(identical(fromJSON, profileFromJSON),
              paste(runner, "preserves profile JSON configuration"))
    }
}

# Existing callers that omit the new option retain caching.
concerto5:::concerto.init(list(), "", "", "", 30, 60, 0, "filesystem",
                         list(), 7, "/tmp/concerto-service/")
check(isTRUE(concerto$cacheEnabled), "Existing init callers retain caching")

# Substitute only the database boundary; exercise the installed getters.
queryCount = 0L
dbEscapeStrings = function(connection, value) value
dbSendQuery = function(connection, sql) {
    queryCount <<- queryCount + 1L
    sql
}
fetch = function(result, n) {
    if (grepl("missing", result, fixed = TRUE)) return(data.frame())
    data.frame(id = 1L, name = "sample", marker = "fresh", type = 0L,
               stringsAsFactors = FALSE)
}
getters = list(tables = concerto.table.get, templates = concerto.template.get,
               tests = concerto.test.get)
for (kind in names(getters)) {
    getter = getters[[kind]]
    concerto$cache = list(tables = list(), templates = list(), tests = list())
    cached = list(id = 1L, name = "sample", marker = "cached")
    concerto$cache[[kind]][["1"]] = cached
    concerto$cache[[kind]][["sample"]] = cached
    concerto$cacheEnabled = TRUE
    before = queryCount
    check(getter(1)$marker == "cached" && queryCount == before,
          paste(kind, "uses cache when enabled"))
    check(getter("sample", cache = FALSE)$marker == "fresh" && queryCount == before + 1L,
          paste(kind, "explicit false bypasses stale cache"))
    concerto$cacheEnabled = FALSE
    check(getter(1)$marker == "fresh" && queryCount == before + 2L,
          paste(kind, "disabled default bypasses stale cache"))
    check(concerto$cache[[kind]][["1"]]$marker == "cached",
          paste(kind, "disabled reads leave cache untouched"))
    check(getter(1, cache = TRUE)$marker == "cached" && queryCount == before + 2L,
          paste(kind, "explicit true overrides disabled default"))
    check(is.null(getter("missing")), paste(kind, "missing uncached object returns NULL"))
    concerto$cacheEnabled = TRUE
    check(is.null(getter("missing")), paste(kind, "missing cached object returns NULL"))
    concerto$cache[[kind]] = list()
    check(getter(1)$marker == "fresh" &&
          concerto$cache[[kind]][["1"]]$marker == "fresh" &&
          concerto$cache[[kind]][["sample"]]$marker == "fresh",
          paste(kind, "populates cache by ID and name"))
}
cat("Completed", checks, "R runtime checks\n")
