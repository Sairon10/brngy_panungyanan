<?php
$c = file_get_contents('dashboard.php');

// Change col-md-12 to col-md-6 for Submitted Card
$c = preg_replace(
    '/<!-- Submitted Card -->\s*<div class="col-md-12">/',
    '<!-- Submitted Card -->
                <div class="col-md-6">',
    $c
);

// Add Rejected Card
$rejected_card = '
                <!-- Rejected Card -->
                <div class="col-md-6">
                    <a href="incidents.php" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card mb-0"
                            style="margin: 0; border: none; background: linear-gradient(135deg, #475569 0%, #334155 100%); box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);">
                            <div class="d-flex align-items-center p-4" style="color: white;">
                                <div class="stats-icon me-3" style="font-size: 2rem; opacity: 0.9;"><i
                                        class="fas fa-ban"></i></div>
                                <div>
                                    <div class="stats-number"
                                        style="font-size: 2rem; font-weight: bold; line-height: 1; margin-bottom: 0.25rem;">
                                        <?php echo $incidents_rejected; ?></div>
                                    <div class="stats-label" style="font-size: 0.9rem; opacity: 0.9;">Rejected</div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>';

$c = preg_replace(
    '/(<!-- Resolved Card -->.*?<\/a>\s*<\/div>)/s',
    '$1' . $rejected_card,
    $c
);

file_put_contents('dashboard.php', $c);
echo "fixed dashboard layout\n";
