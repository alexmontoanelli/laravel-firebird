<?php

namespace Firebird;

use Illuminate\Support\ServiceProvider;
use Firebird\Connection as FirebirdConnection;
use Firebird\FirebirdConnector;
use Illuminate\Database\Connection;


class FirebirdServiceProvider extends ServiceProvider
{
    public function register()
    {
        Connection::resolverFor('firebird', function ($connection, $database, $tablePrefix, $config) {
            return new FirebirdConnection($connection, $database, $tablePrefix, $config);
        });
        $this->app->bind('db.connector.firebird', FirebirdConnector::class);
    }
}