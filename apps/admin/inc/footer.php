<script>
    $(document).ready(function(){
        $('#p_use').click(function(){
          uni_modal("Privacy Policy","policy.php","mid-large")
        })
        window.viewer_modal = function($src = ''){
          start_loader()
          var t = $src.split('.')
          t = t[1]
          if(t =='mp4'){
            var view = $("<video src='"+$src+"' controls autoplay></video>")
          }else{
            var view = $("<img src='"+$src+"' />")
          }
          $('#viewer_modal .modal-content video,#viewer_modal .modal-content img').remove()
          $('#viewer_modal .modal-content').append(view)
          $('#viewer_modal').modal({
                  show:true,
                  backdrop:'static',
                  keyboard:false,
                  focus:true
                })
                end_loader()  

        }
        window.uni_modal = function($title = '' , $url='',$size=""){
            start_loader()
            $.ajax({
                url:$url,
                error:err=>{
                    console.log()
                    alert("An error occured")
                },
                success:function(resp){
                    if(resp){
                        $('#uni_modal .modal-title').html($title)
                        $('#uni_modal .modal-body').html(resp)
                        if($size != ''){
                            $('#uni_modal .modal-dialog').addClass($size+'  modal-dialog-centered')
                        }else{
                            $('#uni_modal .modal-dialog').removeAttr("class").addClass("modal-dialog modal-md modal-dialog-centered")
                        }
                        $('#uni_modal').modal({
                          show:true,
                          backdrop:'static',
                          keyboard:false,
                          focus:true
                        })
                        end_loader()
                    }
                }
            })
        }
        window._conf = function($msg='',$func='',$params = []){
           $('#confirm_modal #confirm').attr('onclick',$func+"("+$params.join(',')+")")
           $('#confirm_modal .modal-body').html($msg)
           $('#confirm_modal').modal('show')
        }

        window.uni_modal_right = function($title = '' , $url='',$size=""){
            start_loader()
            $.ajax({
                url:$url,
                error:err=>{
                    console.log()
                    alert("An error occured")
                },
                success:function(resp){
                    if(resp){
                        $('#uni_modal_right .modal-title').html($title)
                        $('#uni_modal_right .modal-body').html(resp)
                        if($size != ''){
                            $('#uni_modal_right .modal-dialog').addClass($size+'  modal-dialog-centered')
                        }else{
                            $('#uni_modal_right .modal-dialog').removeAttr("class").addClass("modal-dialog modal-md modal-dialog-centered")
                        }
                        $('#uni_modal_right').modal({
                          show:true,
                          backdrop:'static',
                          keyboard:false,
                          focus:true
                        })
                        end_loader()
                    }
                }
            })
        }
    })

</script>


        <!--begin::Footer-->
        <div class="footer py-4 d-flex flex-lg-column" id="kt_footer">
            <!--begin::Container-->
            <div class="container-fluid d-flex flex-column flex-md-row align-items-center justify-content-end">
                <!--begin::Copyright-->
                <a href="./" target="_blank"> Copyrights © <?php echo date('Y') ?> <?php echo $_settings->info('short_name') ?></a>
                <!--end::Copyright-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Footer-->

        <!--begin::Drawers-->
        <!--begin::Chat drawer-->
        <div id="kt_drawer_chat" class="bg-body" data-kt-drawer="true" data-kt-drawer-name="chat" data-kt-drawer-activate="true" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'300px', 'md': '500px'}" data-kt-drawer-direction="end" data-kt-drawer-toggle="#kt_drawer_chat_toggle" data-kt-drawer-close="#kt_drawer_chat_close">
            <!--begin::Messenger-->
            <div class="card w-100 rounded-0" id="kt_drawer_chat_messenger">
                <!--begin::Card header-->
                <div class="card-header pe-5" id="kt_drawer_chat_messenger_header">
                    <!--begin::Title-->
                    <div class="card-title">
                        <!--begin::User-->
                        <div class="d-flex justify-content-center flex-column me-3">
                            <a href="#" class="fs-4 fw-bolder text-gray-900 text-hover-primary me-1 mb-2 lh-1">Notifikasi</a>
                        </div>
                        <!--end::User-->
                    </div>
                    <!--end::Title-->
                    <!--begin::Card toolbar-->
                    <div class="card-toolbar">
                        <!--begin::Close-->
                        <div class="btn btn-sm btn-icon btn-active-light-primary" id="kt_drawer_chat_close">
                            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                                </svg>
                            </span>
                            <!--end::Svg Icon-->
                        </div>
                        <!--end::Close-->
                    </div>
                    <!--end::Card toolbar-->
                </div>
                <!--end::Card header-->
                <!--begin::Card body-->
                <div class="card-body" id="kt_drawer_chat_messenger_body">
                    <!--begin::Messages-->
                    <div class="scroll-y me-n5 pe-5" data-kt-element="messages" data-kt-scroll="true" data-kt-scroll-activate="true" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_drawer_chat_messenger_header, #kt_drawer_chat_messenger_footer" data-kt-scroll-wrappers="#kt_drawer_chat_messenger_body" data-kt-scroll-offset="0px">
                        <!--begin::Message(in)-->
                        <div class="d-flex justify-content-start mb-10">
                            <div class="d-flex flex-column align-items-start message_barang_kosong">
                                <!-- MESSAGE NYA DISINI get_barang _kosong.php script di headernavbar.php -->
                            </div>
                        </div>
                        <!--end::Message(in)-->
                    </div>
                    <!--end::Messages-->
                </div>
                <!--end::Card body-->
                <!--begin::Card footer-->
                <div class="card-footer pt-4" id="kt_drawer_chat_messenger_footer">
                    <a href="<?php echo base_url.'metronic-theme/?page=apage/sales-order/out_of_stock' ?>" class="text-dark text-hover-primary fs-7">Lihat Semua Notifikasi</a>
                </div>
                <!--end::Card footer-->
            </div>
            <!--end::Messenger-->
        </div>
        <!--end::Chat drawer-->
        <!--end::Drawers-->

          
        <!--begin::Scrolltop-->
        <div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true">
            <!--begin::Svg Icon | path: icons/duotune/arrows/arr066.svg-->
            <span class="svg-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="13" y="6" width="13" height="2" rx="1" transform="rotate(90 13 6)" fill="black" />
                    <path d="M12.5657 8.56569L16.75 12.75C17.1642 13.1642 17.8358 13.1642 18.25 12.75C18.6642 12.3358 18.6642 11.6642 18.25 11.25L12.7071 5.70711C12.3166 5.31658 11.6834 5.31658 11.2929 5.70711L5.75 11.25C5.33579 11.6642 5.33579 12.3358 5.75 12.75C6.16421 13.1642 6.83579 13.1642 7.25 12.75L11.4343 8.56569C11.7467 8.25327 12.2533 8.25327 12.5657 8.56569Z" fill="black" />
                </svg>
            </span>
            <!--end::Svg Icon-->
        </div>
        <!--end::Scrolltop-->          
       <div id="view"><?php include "inc/bottomnavbar.php"; ?></div>

        <!--begin::Javascript-->
        <!--begin::Global Javascript Bundle(used by all pages)-->
        <script src="<?php echo base_url ?>assets/plugins/global/plugins.bundle.js"></script>
        <script src="<?php echo base_url ?>assets/js/scripts.bundle.js"></script>
        <!--end::Global Javascript Bundle-->
        <!--begin::Page Vendors Javascript(used by this page)-->
        <!-- <script src="<?php //echo base_url ?>metronic-theme/admin/dist/assets/plugins/custom/datatables/datatables.bundle.js"></script> -->
        <script src="<?php echo base_url ?>assets/plugins/custom/fslightbox/fslightbox.bundle.js"></script>

        <!--end::Page Vendors Javascript-->
        <!--begin::Page Custom Javascript(used by this page)-->
        <script src="<?php echo base_url ?>assets/js/custom/widgets.js"></script>
        <script src="<?php echo base_url ?>assets/js/custom/apps/chat/chat.js"></script>


        <!-- summernote -->
        <link rel="stylesheet" href="<?php echo base_url ?>theme//plugins/summernote/summernote-lite.min.css">
        <!-- Summernote -->
        <script src="<?php echo base_url ?>theme/plugins/summernote/summernote-lite.min.js"></script>


        <!-- Datatables -->
        <script src="<?php echo base_url ?>theme/plugins/datatables/jquery.dataTables.min.js"></script>
        <script src="<?php echo base_url ?>theme/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
        <script src="<?php echo base_url ?>theme/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
        <script src="<?php echo base_url ?>theme/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

        <script src="<?php echo base_url ?>theme/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
        <script src="<?php echo base_url ?>theme/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
        <script src="<?php echo base_url ?>theme/plugins/jszip/jszip.min.js"></script>
        <script src="<?php echo base_url ?>theme/plugins/pdfmake/pdfmake.min.js"></script>
        <script src="<?php echo base_url ?>theme/plugins/pdfmake/vfs_fonts.js"></script>
        <script src="<?php echo base_url ?>theme/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
        <script src="<?php echo base_url ?>theme/plugins/datatables-buttons/js/buttons.print.min.js"></script>
        <script src="<?php echo base_url ?>theme/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
    
        <!-- jQuery UI 1.11.4 Autocomplete-->
        <script src="<?php echo base_url ?>theme/plugins/jquery-ui/jquery-ui.min.js"></script>

        <script>
          if (window.installCsrfProtection) {
            window.installCsrfProtection(window.jQuery);
          }
        </script>
    

