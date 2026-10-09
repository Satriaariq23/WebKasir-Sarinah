function start_loader(){
	$('body').append('<div id="preloader"><div class="loader-holder"><div></div><div></div><div></div><div></div>')
}
function end_loader(){
	 $('#preloader').fadeOut('fast', function() {
		$('#preloader').remove();
      })
}
// function 
window.alert_toast= function($msg = 'TEST',$bg = 'success' ,$pos=''){
	   	 var Toast = Swal.mixin({
	      toast: true,
	      position: $pos || 'top-end',
	      showConfirmButton: false,
	      timer: 5000
	    });
	      Toast.fire({
	        icon: $bg,
	        title: $msg
	      })
	  }

$(document).ready(function(){
	// Login
	$('#login-frm').submit(function(e){
		e.preventDefault()
		start_loader()
		if($('.err_msg').length > 0)
			$('.err_msg').remove()
		$.ajax({
			url:_base_url_+'classes/Login.php?f=login',
			method:'POST',
			data:$(this).serialize(),
			error:err=>{
				console.log(err)

			},
			success:function(resp){
				if(resp){
					resp = JSON.parse(resp)
					if(resp.status == 'success'){
						var redirectUrl = _base_url_+'admin';
						if(resp.level_id == 3){
							redirectUrl = _base_url_+'admin/?page=penjualan';
						}
						location.replace(redirectUrl);
					}else if(resp.status == 'incorrect'){
						var _frm = $('#login-frm')
						var _msg = "<div class='alert alert-danger text-white err_msg'><i class='fa fa-exclamation-triangle'></i> Username atau password salah!</div>"
						_frm.prepend(_msg)
						_frm.find('input').addClass('is-invalid')
						$('[name="username"]').focus()
					}
						end_loader()
				}
			}
		})
	})
	//Establishment Login
	$('#flogin-frm').submit(function(e){
		e.preventDefault()
		start_loader()
		if($('.err_msg').length > 0)
			$('.err_msg').remove()
		$.ajax({
			url:_base_url_+'classes/Login.php?f=flogin',
			method:'POST',
			data:$(this).serialize(),
			error:err=>{
				console.log(err)

			},
			success:function(resp){
				if(resp){
					resp = JSON.parse(resp)
					if(resp.status == 'success'){
						location.replace(_base_url_+'faculty');
					}else if(resp.status == 'incorrect'){
						var _frm = $('#flogin-frm')
						var _msg = "<div class='alert alert-danger text-white err_msg'><i class='fa fa-exclamation-triangle'></i> Username atau password salah.</div>"
						_frm.prepend(_msg)
						_frm.find('input').addClass('is-invalid')
						$('[name="username"]').focus()
					}
						end_loader()
				}
			}
		})
	})

	//user login
	$('#slogin-frm').submit(function(e){
		e.preventDefault()
		start_loader()
		if($('.err_msg').length > 0)
			$('.err_msg').remove()
		$.ajax({
			url:_base_url_+'classes/Login.php?f=slogin',
			method:'POST',
			data:$(this).serialize(),
			error:err=>{
				console.log(err)

			},
			success:function(resp){
				if(resp){
					resp = JSON.parse(resp)
					if(resp.status == 'success'){
						location.replace(_base_url_+'student');
					}else if(resp.status == 'incorrect'){
						var _frm = $('#slogin-frm')
						var _msg = "<div class='alert alert-danger text-white err_msg'><i class='fa fa-exclamation-triangle'></i> Incorrect username or password</div>"
						_frm.prepend(_msg)
						_frm.find('input').addClass('is-invalid')
						$('[name="username"]').focus()
					}
						end_loader()
				}
			}
		})
	})
	// System Info
	$('#system-frm').submit(function(e){
		e.preventDefault()
		start_loader()
		if($('.err_msg').length > 0)
			$('.err_msg').remove()
		$.ajax({
			url:_base_url_+'classes/SystemSettings.php?f=update_settings',
			data: new FormData($(this)[0]),
		    cache: false,
		    contentType: false,
		    processData: false,
		    method: 'POST',
		    type: 'POST',
			success:function(resp){
				var result = resp
				if (typeof resp === 'string') {
					try {
						result = JSON.parse(resp)
					} catch (e) {
						result = { status: resp == 1 ? 'success' : 'failed' }
					}
				}

				if(result && result.status === 'success'){
					alert_toast(result.msg || 'Pengaturan berhasil disimpan', 'success')
					setTimeout(function(){
						location.reload()
					}, 600)
				}else{
					var message = (result && result.msg) ? result.msg : 'Pengaturan gagal disimpan'
					$('#msg').html('<div class="alert alert-danger err_msg"></div>')
					$('#msg .err_msg').text(message)
					end_loader()
				}
			},
			error:function(xhr){
				var message = 'Pengaturan gagal disimpan'
				if (xhr.responseJSON && xhr.responseJSON.msg) {
					message = xhr.responseJSON.msg
				}
				$('#msg').html('<div class="alert alert-danger err_msg"></div>')
				$('#msg .err_msg').text(message)
				end_loader()
			}
		})
	})
})
