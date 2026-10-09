<?php
require_once('sess_auth.php');
?>

<head>
    <meta charset="utf-8">
    <title><?php echo $_settings->info('title') != false ? $_settings->info('title') . ' | ' : '' ?><?php echo $_settings->info('name') ?></title>
    <link rel="icon" href="<?php echo validate_image($_settings->info('logo')) ?>" />


    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <meta name="csrf-token" content="<?php echo e(csrf_token()) ?>" />

    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <!--end::Fonts-->

    <!--begin::Global Stylesheets Bundle(used by all pages)-->
    <link href="<?php echo base_url ?>assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url ?>assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->

    <!-- jQuery  3.5.1-->
    <script src="../theme/plugins/jquery/jquery.min.js"></script>

    <!-- jQuery UI 1.11.4 -->
    <!-- <script src="../plugins/jquery-ui/jquery-ui.min.js"></script> -->

    <!-- jQuery UI aUTOCOMPLETE -->
    <link rel="stylesheet" type="text/css" href="<?php echo base_url ?>theme/plugins/jquery-ui/jquery-ui.css" />

    <!-- SweetAlert2 -->
    <script src="<?php echo base_url ?>theme/plugins/sweetalert2/sweetalert2.min.js"></script>
    <link rel='stylesheet' href='<?php echo base_url ?>theme/plugins/sweetalert2/sweetalert2.min.css'>

    <!-- Toastr -->
    <script src="<?php echo base_url ?>theme/plugins/toastr/toastr.min.js"></script>
    <link rel='stylesheet' href='<?php echo base_url ?>theme/plugins/toastr/toastr.min.css'>

    <!-- Select2 -->
    <link rel="stylesheet" href="<?php echo base_url ?>theme/plugins/select2/css/select2.min.css" />
    <script src="<?php echo base_url ?>theme/plugins/select2/js/select2.full.min.js"></script>

    <!-- jQuery 3.2.1-->
    <!-- <script src="../plugins/jquery/jquery-3.2.1.min.js"></script> -->
    <!-- <link href="<?php echo base_url ?>theme/plugins/bootstrap-datepicker/css/bootstrap-datepicker.min.css" rel="stylesheet"> -->

    <!-- DataTables -->
    <link rel="stylesheet" href="<?php echo base_url ?>theme/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo base_url ?>theme/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo base_url ?>theme/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">


    <script>
        var _base_url_ = '<?php echo base_url ?>';
        var _csrf_token_ = '<?php echo e(csrf_token()) ?>';
    </script>

    <script>
        (function() {
            function addCsrfHeader(headers) {
                headers = headers || {};
                if (headers instanceof Headers) {
                    headers.set('X-CSRF-Token', _csrf_token_);
                    return headers;
                }
                headers['X-CSRF-Token'] = _csrf_token_;
                return headers;
            }

            window.installCsrfProtection = function(jq) {
                jq = jq || window.jQuery;
                if (!jq || !jq.ajaxSetup) {
                    return;
                }

                jq.ajaxSetup({
                    headers: {
                        'X-CSRF-Token': _csrf_token_
                    }
                });

                if (!jq.__csrfPrefilterInstalled && jq.ajaxPrefilter) {
                    jq.ajaxPrefilter(function(options, originalOptions, xhr) {
                        var method = (options.type || options.method || 'GET').toUpperCase();
                        if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                            xhr.setRequestHeader('X-CSRF-Token', _csrf_token_);
                        }
                    });
                    jq.__csrfPrefilterInstalled = true;
                }
            };

            window.installCsrfProtection(window.jQuery);

            if (window.fetch) {
                var nativeFetch = window.fetch;
                window.fetch = function(input, init) {
                    init = init || {};
                    var method = (init.method || 'GET').toUpperCase();
                    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                        init.headers = addCsrfHeader(init.headers);
                    }
                    return nativeFetch(input, init);
                };
            }
        })();
    </script>

    <script src="<?php echo base_url ?>theme/assets/script.js"></script>
    <script src="<?php echo base_url ?>assets/js/sarinah-number.js"></script>
    <link rel="stylesheet" href="<?php echo base_url ?>assets/css/loading.css">


</head>


<script>
    var loader = $('<div id="background-loading">')
    loader.html('<div class="loader"><div></div><div></div><div></div><div></div></div>')

    function start_loader() {
        $('body').find('#background-loading').remove()
        $('body').prepend(loader)
    }

    function end_loader() {
        $('body').find('#background-loading').remove()
    }
    $(function() {
        setTimeout(() => {
            end_loader()
        }, 500);
    })
</script>
