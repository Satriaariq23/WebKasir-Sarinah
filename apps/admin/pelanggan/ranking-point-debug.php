<?php
/**
 * DEBUG VERSION - Ranking Point dengan debug info
 */

// Simulasi base_url
define('base_url', 'http://localhost/sarinah-apps/apps/admin/');

?>

<div class="content-wrapper py-3 px-3">
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">🔴 DEBUG MODE - Status Check</h5>
                </div>
                <div class="card-body">
                    <pre id="debug-output"></pre>
                    <button class="btn btn-primary" onclick="testAjax()">Test AJAX Call</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-header">
                    <h3>Ranking Point Pelanggan</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="table-ranking" class="table table-hover table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 5%;">Ranking</th>
                                    <th style="width: 8%;">Kode</th>
                                    <th>Nama Member</th>
                                    <th>Alamat</th>
                                    <th>Kota</th>
                                    <th>Telepon</th>
                                    <th class="text-center" style="width: 12%;">Total Point</th>
                                    <th class="text-center" style="width: 15%;">Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../theme/plugins/jquery/jquery.min.js"></script>
<script>
    const baseUrl = "<?php echo base_url; ?>classes/Master.php";

    function testAjax() {
        console.log('Starting AJAX test...');
        console.log('URL: ' + baseUrl + "?f=get_customer_ranking");
        
        $.ajax({
            url: baseUrl + "?f=get_customer_ranking",
            type: "POST",
            data: {
                month: new Date().getMonth() + 1,
                year: new Date().getFullYear(),
                limit: 100,
                _t: new Date().getTime() // bypass cache
            },
            dataType: "json",
            success: function(resp) {
                console.log('AJAX Response:', resp);
                document.getElementById('debug-output').innerText = JSON.stringify(resp, null, 2);
                
                if (resp.status === 'success' && resp.ranking && resp.ranking.length > 0) {
                    let html = '';
                    let ranking = 1;

                    resp.ranking.forEach((item) => {
                        console.log('Processing item:', item);
                        
                        const totalPoints = parseInt(item.total_points) || 0;
                        const finalScore = parseFloat(item.final_score) || 0;
                        const eligibilityStatus = item.eligibility_status || 'NOT_ELIGIBLE';
                        
                        console.log('Item Details:');
                        console.log('  - Total Points: ' + totalPoints);
                        console.log('  - Final Score: ' + finalScore);
                        console.log('  - Eligibility Status: ' + eligibilityStatus);
                        console.log('  - eligibility_status === "ELIGIBLE": ' + (eligibilityStatus === 'ELIGIBLE'));
                        
                        let badgeClass = 'bg-secondary';
                        let statusText = 'BELUM MEMENUHI';

                        // Check eligibility status dari score matching (bukan total points)
                        if (eligibilityStatus === 'ELIGIBLE') {
                            badgeClass = 'bg-success';
                            statusText = 'MEMENUHI SYARAT';
                        }

                        console.log('Final Status: ' + statusText + ' (badge: ' + badgeClass + ')');

                        html += `<tr>
                            <td class="text-center">
                                <span class="badge bg-primary" style="font-size: 13px; padding: 8px 12px;">
                                    <i class="bi bi-trophy"></i> #${ranking}
                                </span>
                            </td>
                            <td><strong>${item.kode || '-'}</strong></td>
                            <td>${item.nama || '-'}</td>
                            <td>${item.alamat || '-'}</td>
                            <td>${item.kota || '-'}</td>
                            <td>${item.nohp1 || item.telpon || '-'}</td>
                            <td class="text-center">
                                <span class="badge bg-info text-white" style="font-size: 12px; padding: 6px 10px;">
                                    ${totalPoints} Poin (Score: ${finalScore})
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge ${badgeClass} text-white" style="font-size: 11px; padding: 6px 8px;">
                                    ${statusText}
                                </span>
                            </td>
                        </tr>`;
                        ranking++;
                    });

                    $('#table-ranking tbody').html(html);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error, xhr);
                document.getElementById('debug-output').innerText = 'Error: ' + error + '\n' + xhr.responseText;
            }
        });
    }

    // Auto load on page ready
    $(document).ready(function() {
        testAjax();
        // Reload every 5 seconds for testing
        setInterval(testAjax, 5000);
    });
</script>
