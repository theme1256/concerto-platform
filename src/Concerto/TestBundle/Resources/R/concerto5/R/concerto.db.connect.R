concerto.db.connect = function(driver, username, password, dbname, host, unix_socket, port){
    concerto.log(paste0("connecting with db using ",driver))
    con = NULL
    if (driver == "pdo_mysql") {
        require("RMySQL")
        connectMySQL = function(default.file = NULL) {
            dbConnect(
                MySQL(),
                username = username,
                password = password,
                dbname = dbname,
                host = host,
                unix.socket = unix_socket,
                port = as.numeric(port),
                default.file = default.file
            )
        }
        con <- tryCatch(connectMySQL(), error = function(e) {
            # MySQL 8 caching_sha2_password: a full (uncached) authentication over an unencrypted
            # connection needs RSA, which the GnuTLS build of the MariaDB client cannot do.
            # Over TLS the password can be sent, so retry encrypted (servers without TLS keep
            # working with the plain connection above).
            if (!grepl("caching_sha2_password", conditionMessage(e), fixed = TRUE)) stop(e)
            concerto.log("caching_sha2_password authentication needs TLS, reconnecting with TLS")
            tlsOptions <- tempfile(fileext = ".cnf")
            on.exit(unlink(tlsOptions))
            writeLines(c("[client]", "ssl-enforce=1"), tlsOptions)
            connectMySQL(default.file = tlsOptions)
        })
        dbExecute(con, statement = 'SET NAMES \"utf8\"')
    } else if (driver == "pdo_sqlite") {
        #require("RSQLite")
        stop("pdo_sqlite driver not implemented yet")
    } else if (driver == "pdo_pgsql") {
        #require("RPostgreSQL")
        stop("pdo_pgsql driver not implemented yet")
    } else if (driver == "pdo_sqlsrv") {
        require("RSQLServer")
        con <- dbConnect(
            RSQLServer::SQLServer(),
            server = host,
            database = dbname,
            port = port,
            properties = list(user = username, password = password)
        )
    } else if (driver == "oci8" || driver == "pdo_oci") {
        #require("ROracle")
        stop("oci8 and pdo_oci driver not implemented yet")
    } else if (driver == "sqlanywhere") {
        stop("sqlanywhere driver not supported yet")
    }

    if (! existsFunction("dbEscapeStrings")) {
        dbEscapeStrings <<- function(con, string){
            return(gsub("'", "''", string))
        }
    }
    if (! existsFunction("dbSendStatement")) {
        dbSendStatement <<- dbSendQuery
    }
    return(con)
}