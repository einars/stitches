<?php

# Page
# ====
#
# Various stuff that's relevant to page rendering and manipulation.
#
#
# Problems:
# currently you cannot customize error page (pretty_error_page) and it's ugly

class Page {

    static $title = 'Untitled';
    static $content_type = 'text/html; charset=utf-8';

    static $custom_head_html = array(); 
    static $body_classes = array(); 

    static $scripts = array(); // { short_name => url, ... }
    static $stylesheets = array(); // [ {url, media, conditiona}, ... ]
    static $style = array(); // [ media => css_code ]

    static $favicon = null;

    static $meta = array(
        'skype_toolbar' => 'skype_toolbar_parser_compatible',
        'charset' => 'utf-8',
        'viewport' => 'width=device-width, initial-scale=1',
    );

    static $onload_js = '';

    static function add_rss($url, $title)
    {
        Page::add_to_head(hs('<link rel="alternate" type="application/rss+xml" title="%s" href="%s">'
            , $title
            , $url
        ));
    }

    static function send_nocache_headers()
    {
        header("Pragma: no-cache");
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: Thu, 01 Jan 2010 00:00:00 GMT");
    }

    static function set_description($description)
    {
        Page::$meta['description'] = strip_tags(str_replace("\n", '', $description));
    }

    static function set_title($title)
    {
        Page::$title = $title;
    }

    static function get_title()
    {
        return Page::$title;
    }

    static function set_meta($key, $meta)
    {
        Page::$meta[$key] = $meta;
    }

    static function get_meta($key)
    {
        return get($key, Page::$meta);
    }

    static function add_to_head($html, $short_name = null)
    {
        if ($short_name === null) {
            Page::$custom_head_html[$short_name]= $html;
        } else {
            Page::$custom_head_html[]= $html;
        }
    }

    static function noindex()
    {
        Page::set_meta('robots', 'noindex, nofollow');
    }

    #
    # Page::add_script
    # ----------------
    #
    # Add a link to some (javascript) file in the page head.
    #
    # Specify the optional $short_name to classify the scripts.
    #
    #   Page::add_script('/path/to/jquery.js', 'jquery');
    #   Page::add_script('/path/to/other.jquery.js', 'jquery'); // replaces previous
    #   Page::add_script('/path/to/jquery-ui.js');
    # 
    static function add_script($url, $short_name = null)
    {
        if ($short_name) {
            Page::$scripts[$type] = $url;
        } else {
            $short_name = basename($url);
            Page::$scripts[$short_name] = $url;
        }
    }

    # Page::add_stylesheet
    # --------------------
    #
    # Add custom stylesheet.
    #
    #   Page::add_stylesheet('/media/site.css');
    #   Page::add_stylesheet('/media/print.css', 'print');
    #   Page::add_stylesheet('/media/ie.css', 'all', 'IE');
    #
    static function add_stylesheet($url, $media = 'all', $conditional = null)
    {
        Page::$stylesheets[] = array(
            'url' => $url,
            'media' => $media,
            'conditional' => $conditional
        );
    }


    # Page::set_content_type
    # ----------------------
    #
    # Change content type for the page.
    # Default: text/html; charset=utf-8
    #
    static function set_content_type($content_type)
    {
        Page::$content_type = $content_type;
    }

    # Page::add_onload_js
    # -------------------
    # Add some short code to be added to the function to be called after the
    # page initialization.
    #
    #   Page::add_onload_js('console.log("Page loaded.");');
    #
    static function add_onload_js($js)
    {
        Page::$onload_js .= $js . "\n";
    }

    # Page::add_style
    # ---------------
    # Add some short css code to be added to <style> section in head.
    #
    #   Page::add_style('.important { font-weight: bold }');
    #
    static function add_style($css_code, $media = 'all')
    {
        Page::$style[$media] = trim(get($media, Page::$style) . "\n" . $css_code);
    }


    # Page::set_favicon
    # -----------------
    # Add a link in page head with favicon.
    #
    static function set_favicon($favicon_url)
    {
        Page::$favicon = $favicon_url;
    }


    # Page::detect_favicon_type
    # -------------------------
    # Used internally to determine correct mime-type for the favicon.
    #
    static function detect_favicon_type()
    {
        switch(substr(Page::$favicon, -4)) {
        case '':
            return null;
        case '.png':
            return 'image/png';
        case '.gif':
            return 'image/gif';
        default:
            return 'image/vnd.microsoft.icon';
        }
    }


    # Page::reload
    # ------------
    # Redirects user back to the same page that's currently requested
    #
    static function reload()
    {
        Page::redirect(get('REQUEST_URI', $_SERVER));
    }


    # Page::redirect
    # --------
    # Redirect to some path. Does not return.
    #
    #  Page::redirect('/logout');
    #
    static function redirect($url = '?', $response_code = 302)
    {
        if (s::cli()) {

            // no redirection under the cli, so just pop out the message
            printf("Redirect: %s\n", $url);

        } else {

            $text_so_far = @ob_get_clean();

            if ($text_so_far) {
                // don't do actual redirect if the page already has any output
                if ( ! headers_sent()) {
                    // set content type just in case
                    header('Content-type: ' . Page::$content_type);
                }
                echo $text_so_far;
                if ($url == '?') {
                    hprintf('%d <a href="%s">refresh</a>', $response_code, $url);
                } else {
                    hprintf('%d redirect to <a href="%s">%s</a>', $response_code, $url, $url);
                }
            }

            if (headers_sent()) {
                // finish the page gracefully
                s::emit('page:body-end');
                s::emit('page:end');
            } else {
                header('Location: ' . absolute_url($url), true, $response_code);
            }
        }
        exit;
    }


    # Page::present
    # ---------------
    # The main render function that takes some content, wraps it into chrome/wrapper
    # (by passing through content event filter) and displays it.
    #
    # You may respond to events like page:head-start, page:head-end,
    # page:body-start, page:body-end to display anything you'd like.
    #  
    static function present($content)
    {
        if ( ! s::cli() && ! headers_sent()) {
            header('Content-type: ' . Page::$content_type);
        }

        # no need for wrappers
        if (Page::is_plain()) {
            echo $content;
            return;
        }

        $content = s::emit('content', $content);

        s::emit('content-ready');

        $html_class = s::get('page:html-class');

        echo "<!DOCTYPE html>\n";
        if ($html_class) {
            h('<html itemscope itemtype="http://schema.org/" lang="en-us" dir="ltr" class="%s">', $html_class);
        } else {
            echo '<html itemscope itemtype="http://schema.org/" lang="en-us" dir="ltr">';
        }

        echo '<head>';
        // you may want to print something in response to page:head-start
        s::emit('page:head-start');

        foreach(Page::$meta as $key=>$meta) {
            if ( ! $meta) continue;

            if ($key === 'charset') {
                h('<meta charset="%s">', $meta);
            } else {
                h('<meta name="%s" content="%s">', $key, $meta);
                if ($key === 'name' || $key === 'description') {
                    h('<meta itemprop="%s" content="%s">', $key, $meta);
                }
            }
        }

        if (Page::$favicon) {
            h('<link rel="icon" href="%s" type="%s" />'
                , Page::$favicon
                , Page::detect_favicon_type()
            );
        }

        h('<title>%s</title>', strip_tags(Page::$title));

        foreach(array_reverse(Page::$stylesheets) as $entry) {
            if ($entry['conditional']) {
                printf("\n<!--[if %s]>\n", $entry['conditional']);
            }
            h('<link rel="stylesheet" href="%s" media="%s">', $entry['url'], $entry['media']);
            if ($entry['conditional']) {
                echo "\n<![endif]-->\n";
            }
        }

        foreach(array_filter(Page::$style) as $media => $css) {
            printf('<style type="text/css" media="%s">%s</style>'
                , $media
                , $css
            );
        }

        if (Page::$scripts) {
            foreach(Page::$scripts as $script) {
                printf('<script type="text/javascript" src="%s"></script>', $script);
            }
        }

        if (Page::$onload_js) {
            echo '<script type="text/javascript">';
            echo 'document.addEventListener("DOMContentLoaded", function () {', Page::$onload_js, ' } )';
            echo '</script>';
        }


        if (Page::$custom_head_html) {
            echo implode("\n", Page::$custom_head_html);
        }

        // you may want to print something in response to page:head-end
        s::emit('page:head-end');
        echo '</head>';

        if (Page::$body_classes) {
            h('<body class="%s">', implode(' ', Page::$body_classes));
        } else {
            echo '<body>';
        }

        s::emit('page:body-start');

        if (failed($content)) {
            print_failure($content);
        } else {
            echo $content;
        }

        s::emit('page:body-end');

        echo '</body>';
        echo '</html>';
    }


    # Page::set_plain_output
    # ----------------------
    # Output custom content_type, disable html wrappers.
    #
    # content won't get called, only your function output will be sent by
    # server.
    #
    # Internally, sets page:plain flag.
    #
    #    function on_check_status()
    #    {
    #      Page::set_plain_output();
    #      echo 'Ok';
    #    }
    #
    static function set_plain_output($content_type = 'text/plain; charset=utf-8')
    {
        if ( ! s::cli() and ! headers_sent()) {
            header('Content-type: ' . $content_type);
        }
        Page::set_content_type($content_type);
        s::set('page:plain');
    }




    # Page::output_json
    # -----------------
    # Output json data
    #
    # You don't need to care for html wrappers etc anymore.
    #
    static function output_json($something, $json_opts = null)
    {
        Page::set_plain_output('text/json');
        echo json_encode($something, any($json_opts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }




    # Page::is_plain
    # --------------
    # Returns true/false, depending on whether current execution
    # context asks for a plain output, or decorated (passed through
    # content filter and html decorations).
    #
    # Plain output is used when:
    #  - app is running from command line
    #  - page::set_plain_output() was called (s::get('page:plain'))
    #  - page was called via xmlhttprequest
    #
    static function is_plain()
    {
        return Page::is_ajax() || s::get('page:plain') || s::cli() || strpos(get('HTTP_CONTENT_TYPE', $_SERVER, ''), 'application/json') !== false;
    }




    # Page::is_ajax
    # --------------
    # Returns true if the page was requested via xmlhttprequest.
    #
    # When it is so, the page is rendered without decorations and without
    # wrapping through content.
    #
    static function is_ajax()
    {
        return strtolower(get('HTTP_X_REQUESTED_WITH', $_SERVER, '')) === 'xmlhttprequest';
    }




    # Page::set_canonical_url
    # -----------------------
    # Adds a <link rel="canonical"> to page head containing
    # page url with a leading '/'. Leading slash is enforced.
    #
    # Initially called by s::run().
    #
    # Repeated calls replace the value set by the previous calls.
    #
    #   Page::set_canonical_url('/foo/');
    #   > <link rel="canonical" href="/foo/">
    #
    #   Page::set_canonical_url('/foo');
    #   > <link rel="canonical" href="/foo/">
    #
    static function set_canonical_url($canonical_url)
    {
        if ( ! $canonical_url) return;

        if (($pos = strpos($canonical_url, '?')) !== false) {
            $canonical_url = substr($canonical_url, 0, $pos);
        }

        $canonical_url = rtrim($canonical_url, '/') . '/';

        Page::add_to_head(sprintf('<link rel="canonical" href="%s">', $canonical_url), 'canonical-url');
    }


    static function pretty_error_page($header, $message)
    {
        header('HTTP/1.0 ' . $header);

        $content = 
            '<h1 style="font-size: 22px; font-family: sans-serif; font-weight: normal;">' . $header . '</h1>';
        $content .=
            hs('<p style="font-size: 12px;">%s</p>', $message);
        echo page::present($content);
    }
}
