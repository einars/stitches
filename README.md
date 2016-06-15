Stitches
========

A collection of techniques that make my php development not just bearable, but
even a bit pleasant.

It's a work in progress as I collect, rethink, document and clean up document
various stuff grown in my projects.

Take a look at the "examples" folder. If there isn't a sample application yet,
then it's probably too early for you to be here.

PHP 5+. error\_reporting(E\_ALL) safe. Intended for nginx or apache and — if
database is required — then postgresql or mysql or sqlite.

Bootstrap is expected in the form helper,  but not strictly required.

No other assumptions, but you probably need mbstring and json modules for your
own sanity.

Written by Einar Lielmanis, einars@spicausis.lv, github: @einars.


Philosophy
----------

I find most of the frameworks over-architected and over-engineered, with class
design spanning folders and folders of code, making simple things needlessly
complex and verbose.

Php classes here are used mostly as very, very convenient autoloading
namespaces for organizing static functions.

There is no MVC here. There's a tools/model-generator.php for a nice, simple
model class generation from the database, but no templates or model-view
separation. Php is good for that.

HTML code in functions is nothing to be ashamed of.

Writing direct SQL is good.

HTML::* and Form::* help with most of the form stuff. It's bootstrap-ish, but
customizable at that.


Stuff
-----

- Events

  Most actions are done through events, and callbacks may be assigned to them.

  When your app responds to some route and prints "<h1>Hello world</h1>", the
  displayed output will get filtered through the event called 'content', and
  the functions attached to this event will decorate output, until you get a
  full-fledged valid html page, with &lt;head>, and &lt;body> and all the rest
  of html.


- Routing

  Application gathers routes by emiting event 'route', and the listeners
  supplement the route array with their own routes and handlers.

    function add\_some\_routes($routes) {
      $routes['some_url'] = 'on_some_url';
      $routes['some_other_url'] = function () {
        echo 'Hello world';
      }
      return $routes;
    }

    s::on('routes', 'add_some_routes');


- Installer

  WRITEME


- Form helper

  WRITEME


- Error logging

  WRITEME


- Access control

  WRITEME


- Database access

  Use from anywhere: 

    db::query('select * from users where user_name=%s', 'john');
  
  Using "db" autoloads the class, connects to the database if not previously
  connected, and offers safe, printf-like syntax for querying data.


- Database-backed configuration

  c.config.php
  WRITME


- Convenience functions

  - convention for optional function parameters, options.php

  - convenention for signalling errors returned from functions, failures.php

  - helper functions in common.php


