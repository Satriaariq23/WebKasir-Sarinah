<style>
	img#cimg{
		height: 15vh;
		width: 15vh;
		object-fit: scale-down;
		border-radius: 100% 100%;
	}
	img#cimg2{
		height: 50vh;
		width: 100%;
		object-fit: contain;
		/* border-radius: 100% 100%; */
	}
</style>

<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card card-bordered">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <a href="#" class="text-dark fw-bolder fs-3">System Info</a>
                    </div>
                </div>
                <div class="separator"></div>
			    <div class="card-body">
					<form action="" id="system-frm">
		                <div id="msg" class="form-group"></div>
		                <div class="row">
			                <div class="col-md-6 mb-4">
			                	<div class="form-group">
			                        <label class="fw-bold fs-6 mb-0">Nama Toko</label>
			                        <input type="text" class="form-control" name="name" id="name" value="<?php echo  $_settings->info('name') ?>">
			                    </div>
			                </div>
			                <div class="col-md-6 mb-4">
			                	<div class="form-group">
			                        <label class="fw-bold fs-6 mb-0">Nama Aplikasi</label>
			                        <input type="text" class="form-control" name="short_name" id="short_name" value="<?php echo $_settings->info('short_name') ?>">
			                    </div>
			                </div>			                
			            </div>
			            <div class="row">
			                <div class="col-md-6 mb-4">
				                <div class="form-group">
			                        <label class="fw-bold fs-6 mb-0">Alamat</label>
			                        <input type="text" class="form-control" name="alamat" id="alamat" value="<?php echo  $_settings->info('alamat') ?>">
		                        </div>
		                    </div>
		                    <div class="col-md-6 mb-4">
		                        <div class="form-group">
			                        <label class="fw-bold fs-6 mb-0">Nama Sistem</label>
			                        <input type="text" class="form-control" name="title" id="title" value="<?php echo  $_settings->info('title') ?>">
			                    </div>
			                </div>
			            </div>
                        <div class="row">  
							<div class="col-md-6 mb-4">
		                        <div class="form-group">
			                        <label class="fw-bold fs-6 mb-0">Email</label>
			                        <input type="email" class="form-control" name="email" id="email" value="<?php echo $_settings->info('email') ?>">
			                    </div>
			                 </div>
			             <!-- </div> -->
			             <!-- <div class="row"> -->
							<div class="col-md-6 mb-4">						
			                    <div class="form-group">
			                    	<label class="fw-bold fs-6 mb-0">No. Telp</label>
			                        <input type="text" class="form-control" name="telpon" id="telpon" value="<?php echo  $_settings->info('telpon') ?>">
		                        </div>
		                  	</div>
		                </div>
		                <?php
		                $salesProfitPercent = $_settings->info('sales_profit_percent');
		                if ($salesProfitPercent === false || $salesProfitPercent === '') {
		                	$salesProfitPercent = 100;
		                }
		                $ppnRate = $_settings->info('ppn_rate');
		                if ($ppnRate === false || $ppnRate === '') {
		                	$ppnRate = 11;
		                }
		                ?>
		                <div class="row">
			                <div class="col-md-6 mb-4">
			                	<div class="form-group">
				                        <label class="fw-bold fs-6 mb-2" for="sales_profit_percent">
				                        	Persentase Default Profit Sales
			                        </label>
			                        <div class="input-group">
				                        <input
				                        	type="number"
				                        	class="form-control"
				                        	name="sales_profit_percent"
				                        	id="sales_profit_percent"
				                        	min="0"
				                        	max="100"
				                        	step="0.01"
				                        	required
				                        	value="<?php echo e($salesProfitPercent) ?>"
				                        >
				                        <span class="input-group-text">%</span>
			                        </div>
			                        <small class="form-text text-muted">
				                        	Dipakai sebagai nilai awal/fallback. Persentase tiap sales diatur pada Master Data Salesman.
			                        </small>
			                    </div>
			                </div>
			                <div class="col-md-6 mb-4">
			                	<div class="form-group">
			                        <label class="fw-bold fs-6 mb-2" for="ppn_rate">
			                        	Tarif PPN Barang
			                        </label>
			                        <div class="input-group">
				                        <input
				                        	type="number"
				                        	class="form-control"
				                        	name="ppn_rate"
				                        	id="ppn_rate"
				                        	min="0"
				                        	max="100"
				                        	step="0.01"
				                        	required
				                        	value="<?php echo e($ppnRate) ?>"
				                        >
				                        <span class="input-group-text">%</span>
			                        </div>
			                        <small class="form-text text-muted">
			                        	Diterapkan hanya pada barang yang dicentang PPN.
			                        	Harga transaksi disimpan termasuk PPN.
			                        </small>
			                    </div>
			                </div>
			            </div>
            			<div class="form-group">
					        <label class="fw-bold fs-6 mb-2">Logo (400px x 130px)</label>            				
							<div class="custom-file">
				              <input type="file" class="custom-file-input" id="customFile" name="img" onchange="displayImg(this,$(this))">
				            </div>
						</div>
		                <div class="form-group d-flex justify-content-center mb-4">
		                    <img src="<?php echo validate_image($_settings->info('logo')) ?>" alt="" id="cimg" class="img-fluid img-thumbnail">
		                </div>			    	                
		            </form>
			    </div>
				<div class="card-footer d-flex justify-content-end">
					<button class="btn btn-primary" form="system-frm">Update</button>
				</div>
			</div>
		</div>
	</div>
</div>



<script>
	function displayImg(input,_this) {
	    if (input.files && input.files[0]) {
	        var reader = new FileReader();
	        reader.onload = function (e) {
	        	$('#cimg').attr('src', e.target.result);
	        	_this.siblings('.custom-file-label').html(input.files[0].name)
	        }

	        reader.readAsDataURL(input.files[0]);
	    }
	}
	function displayImg2(input,_this) {
	    if (input.files && input.files[0]) {
	        var reader = new FileReader();
	        reader.onload = function (e) {
	        	_this.siblings('.custom-file-label').html(input.files[0].name)
	        	$('#cimg2').attr('src', e.target.result);
	        }

	        reader.readAsDataURL(input.files[0]);
	    }
	}
	function displayImg3(input,_this) {
	    if (input.files && input.files[0]) {
	        var reader = new FileReader();
	        reader.onload = function (e) {
	        	_this.siblings('.custom-file-label').html(input.files[0].name)
	        	$('#cimg3').attr('src', e.target.result);
	        }

	        reader.readAsDataURL(input.files[0]);
	    }
	}

</script>
