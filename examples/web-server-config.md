Web server configuration to use pretty urls
===========================================

For routing, the unhandled urls need to be rewritten to ?action=$request

I.e /page/foo should go to /index.php?action=/page/foo

Nginx configuration follows:

Nginx
-----

Nginx has mod\_rewrite enabled by default, so use:

    server {
        listen 80;
        server_name example.com;
        root /services/web/example.com;

        index index.php index.html;

        location @rules {
            rewrite ^/([^?]*)(?:\?(.*))? /index.php?action=$1&$2 last;
        }

        location / {
            try_files $uri $uri/ @rules;
        }

        location ~ \.php$ {

            try_files $uri $uri/ @rules;

            # fastcgi, or whatever's your php configuration:
            fastcgi_pass 127.0.0.1:2999;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME  $document_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 600;

        }

    }

You'll need to consult your OS manuals for the preferred way to store this —
maybe you can shove it into /etc/nginx/nginx.conf, or maybe it's preferred
somewhere else (/etc/nginx/sites-enabled, or something similar).

Apache
------

The following is a basic .htaccess file:

    #Options FollowSymlinks
    RewriteEngine On

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?action=$1 [QSA]


You'll probably want to put the file in the webroot of your webapp, aside
index.php.

You might need to comment/uncomment "FollowSymlinks" and/or RewriteEngine
option, or maybe enable mod\_rewrite module, depending on your OS and nuances
of the server configuration.
