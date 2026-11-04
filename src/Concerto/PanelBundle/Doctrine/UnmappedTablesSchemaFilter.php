<?php

namespace Concerto\PanelBundle\Doctrine;

/**
 * Hides every database asset from Doctrine's schema tooling unless the ORM maps it.
 *
 * ORM 3 removed the "save mode" of doctrine:schema:update: it diffs the whole database and
 * drops whatever the ORM does not map. Concerto keeps user data tables (and tables created from
 * R code) in the same database, and concerto:setup runs the update on every start. The ORM's
 * SchemaTool always whitelists the tables and sequences it maps on top of this filter, so with
 * it schema updates create/alter ORM tables and never see, let alone drop, anything else.
 *
 * Code that needs to see every table (data tables) uses DBStructureDAO::withAllTables().
 */
class UnmappedTablesSchemaFilter
{
    public function __invoke($asset): bool
    {
        return false;
    }
}
