<?php
/**
 *
 * The DS_Runtime object provides our DesktopServer runtime with the ability to
 * execute installable plugins from the ds-plugins folder. Early binding prepends
 * can be executed in addition to globally accessible WordPress API based plugins
 * across all virtual hosts.
 *
 * @package DesktopServer
 * @since 3.8.0
 */

if ( PHP_SAPI === 'cli' ) {
  // Process CLI invoked last_ui_event; update_server
  if (isset($argv[1])) {
    if (substr($argv[1], 0, 11)=="ds_runtime=") {
      $_GET["ds_runtime"] = urldecode( str_replace("~", "%", substr($argv[1], 11, strlen($argv[1]))) );
    }
  }
}
if ( ! class_exists( 'DS_Runtime' ) ) {
    class DS_Runtime {
        public $preferences = null;        // Contains the preferences file in its entirety.
        public $last_ui_event = false;     // Receive action from DS app via GET serialized message. ie:
                                           // http://localhost/?ds_runtime={"action":"site_imported", "sites":["www.example.dev"]}
        public $ds_filters = [];           // Support for code injection in our localhost index.php
        public $ds_filter_count = 0;
        public $prepends = [];
        public $is_localhost = false;      // Convenience check for localhost
        public $htdocs_dir   = "";
        public $ds_plugins_dir = "";


        public function __construct() {
            if ( isset($_SERVER['HTTP_HOST'] ) ) {
              if ( $_SERVER['HTTP_HOST']=="127.0.0.1" || $_SERVER['HTTP_HOST']=="localhost" ) {
                $this->is_localhost = true;
              }
            }

            // Read json preferences file based on platform
            global $pref;
            $pref = null;
            if ( PHP_OS !== 'Darwin' ){
                $pref = 'c:/ProgramData/DesktopServer/com.serverpress.desktopserver.json';
                $this->ds_plugins_dir = "c:/xampplite/ds-plugins";
                $this->htdocs_dir = "c:/xampplite/htdocs";
            }else{
                $pref = '/Users/Shared/.com.serverpress.desktopserver.json';
                $this->ds_plugins_dir = "/Applications/XAMPP/ds-plugins";
                $this->htdocs_dir = "/Applications/XAMPP/htdocs";
            }
            if ( !file_exists( $pref ) ) return;
            $this->preferences = json_decode( file_get_contents( $pref ) );

            // Fill out $last_ui_event if present
            if ( isset( $_GET['ds_runtime'] ) ) {
              $this->last_ui_event = json_decode( $_GET['ds_runtime'] );
              
              // Exit early (no WP runtime present, just last_ui_event)
              return;
            }
            if ( true === $this->is_localhost ) {
              // Exit early if on localhost or 127.0.0.1
              return;
            }
    
            // Brute force hook muplugins_loaded event
            global $wp_filter;
            $wp_filter['muplugins_loaded'][0]['ds_load_plugins'] = array( 'function' =>  array(&$this, 'muplugins_loaded'), 'accepted_args' => 3 );

            // Brute force hook installer's wp_print_scripts
            if ( strpos( $_SERVER['SCRIPT_NAME'], 'wp-admin/install.php' ) !== FALSE ) {
                $wp_filter['wp_print_scripts'][999]['ds_install_print_scripts'] = array( 'function' =>  array(&$this, 'ds_install_print_scripts'), 'accepted_args' => 1 );
            }
        }
        public function ds_install_print_scripts( $script ) {
            // Check "Discourage search engines..."  or un-check "Allow search engines..." checkbox
            ?>
            <script type="text/javascript">
                setTimeout(function(){
                    (function($){
                        $(function(){
                            <?php
                            global $wp_version;
                            if ( version_compare ( $wp_version , '4.4', '>=' ) ): ?>
                            $("input[name='blog_public']:checkbox").prop('checked',true).closest('tr').hide();
                            <?php else: ?>
                            $("input[name='blog_public']:checkbox").prop('checked',false).closest('tr').hide();
                            <?php endif; ?>
                        });
                    })(jQuery);
                }, 50);
            </script>
            <?php
        }
        public function muplugins_loaded() {
            // Hook plugins_url to correct for ds-plugins folder
            add_filter( 'plugins_url', array(&$this, 'plugins_url'), 0 );

            // Load any enabled plugins
            foreach ( $this->preferences->{'ds-plugins'} as $name => $file ) {
                if ( file_exists( $file ) ) {
                    include_once( $file );
                }
            }
        }
        public function plugins_url( $url ) {
            if ( strpos( $url, 'ds-plugins' ) ) {
                $new = $this->getLeftMost( $url, '//' ) . '//';
                $url = $this->delLeftMost( $url, '//' );
                $new .= $this->getLeftMost( $url, '/' );
                $new .= '/ds-plugins' . $this->delLeftMost( $url, '/ds-plugins' );
                $url = $new;
            }
            return $url;
        }

        /**
         * Load any enabled plugins that have prepends.php or prepends_#.php
         * (where # is lower number load earlier, default is 5 for prepends.php).
         * We do this after our ds_runtime object is created (so prepends can
         * also access it)
         */
        public function processPrepends() {
            for ( $n = 0; $n <= 10; $n++ ) {
                if ( isset( $this->preferences->{'ds-plugins'} ) && is_object( $this->preferences->{'ds-plugins'} )) {
                    foreach ( $this->preferences->{'ds-plugins'} as $name => $file ) {
                        $file    = str_replace( "\\", '/', $file );
                        $prepend = $this->delRightMost( $file, '/' ) . '/prepend';
                        if ( file_exists( $prepend . '_' . $n . '.php' ) ) {
                            array_push( $this->prepends, $prepend . '_' . $n . '.php' );
                        }
                        if ( $n === 5 && file_exists( $prepend . '.php' ) ) {
                            array_push( $this->prepends, $prepend . '.php' );
                        }
                    }
                }
            }
            foreach( $this->prepends as $prepend ) {
                include_once( $prepend );
            }
        }

        /**
         * Execute functions hooked on a specific action hook.
         *
         * This function invokes all functions attached to action hook `$tag`. It is
         * possible to create new action hooks by simply calling this function,
         * specifying the name of the new hook using the `$tag` parameter.
         *
         * @param string $tag The name of the action to be executed.
         * @param mixed  $arg Optional. Additional arguments which are passed on to the
         *                    functions hooked to the action. Default empty.
         *
         * @return null
         */
        public function do_action( $tag, $arg = '' ) {
            if ( ! isset( $this->ds_filters[$tag] ) ) return;

            $args = array();
            if ( is_array($arg) && 1 == count($arg) && isset($arg[0]) && is_object($arg[0]) ) // array(&$this)
                $args[] =& $arg[0];
            else
                $args[] = $arg;
            for ( $a = 2, $num = func_num_args(); $a < $num; $a++ )
                $args[] = func_get_arg($a);

            foreach ( $this->ds_filters[$tag] as $func ) {
                call_user_func_array( $func, $args );
            }
        }

        /**
         * Hook a function or method to a specific filter action.
         *
         * @param string $tag The name of the action to add.
         * @param callback $function_to_add The function to be invoked.
         *
         * @return bool
         */
        public function add_action( $tag, $function_to_add ) {
            $idx = $tag . '_' . $this->ds_filter_count;
            $this->ds_filter_count++;
            $this->ds_filters[$tag][$idx] = $function_to_add;
            return true;
        }

        // Utility functions
        private function delRightMost( $sSource, $sSearch ) {
            for ($i = strlen($sSource); $i >= 0; $i = $i - 1) {
                $f = strpos($sSource, $sSearch, $i);
                if ($f !== FALSE) {
                    return substr($sSource,0, $f);
                    break;
                }
            }
            return $sSource;
        }
        private function delLeftMost( $sSource, $sSearch ) {
            for ( $i = 0; $i < strlen( $sSource ); $i = $i + 1 ) {
                $f = strpos( $sSource, $sSearch, $i );
                if ( $f !== false ) {
                    return substr( $sSource, $f + strlen( $sSearch ), strlen( $sSource ) );
                    break;
                }
            }
            return $sSource;
        }
        private function getLeftMost( $sSource, $sSearch ){
            for ( $i = 0; $i < strlen( $sSource ); $i = $i + 1 ) {
                $f = strpos( $sSource, $sSearch, $i );
                if ( $f !== false ) {
                    return substr( $sSource, 0, $f );
                    break;
                }
            }
            return $sSource;
        }
    }
    global $ds_runtime;
    $ds_runtime = new DS_Runtime();
    $ds_runtime->processPrepends();
}
