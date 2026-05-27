<?php
$c = file_get_contents('dashboard.php');

// Add to query section
$c = str_replace(
    "\$incidents_resolved = (int) (\$pdo->query(\"SELECT COUNT(*) as c FROM incidents WHERE user_id=\$user_id AND status='resolved'\")->fetch()['c'] ?? 0);",
    "\$incidents_resolved = (int) (\$pdo->query(\"SELECT COUNT(*) as c FROM incidents WHERE user_id=\$user_id AND status='resolved'\")->fetch()['c'] ?? 0);\n    \$incidents_rejected = (int) (\$pdo->query(\"SELECT COUNT(*) as c FROM incidents WHERE user_id=\$user_id AND status='rejected'\")->fetch()['c'] ?? 0);",
    $c
);

// Add to catch block
$c = str_replace(
    "\$incidents_resolved = 0;",
    "\$incidents_resolved = 0;\n    \$incidents_rejected = 0;",
    $c
);

// Fix layout col-md-12 to col-md-6 for Submitted
$c = str_replace(
    '<!-- Submitted Card -->
                <div class="col-md-12">',
    '<!-- Submitted Card -->
                <div class="col-md-6">',
    $c
);

// Add Rejected Card after Resolved Card
$resolved_card_end = '<!-- Resolved Card -->
                <div class="col-md-6">
                    <a href="incidents.php" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card success mb-0"
                            style="margin: 0; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);">
                            <div class="d-flex align-items-center p-4" style="color: white;">
                                <div class="stats-icon me-3" style="font-size: 2rem; opacity: 0.9;"><i
                                        class="fas fa-check-double"></i></div>
                                <div>
                                    <div class="stats-number"
                                        style="font-size: 2rem; font-weight: bold; line-height: 1; margin-bottom: 0.25rem;">
                                        <?php echo $incidents_resolved; ?></div>
                                    <div class="stats-label" style="font-size: 0.9rem; opacity: 0.9;">Resolved</div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>';

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

$c = str_replace($resolved_card_end, $resolved_card_end . $rejected_card, $c);

// Update chart empty check
$c = str_replace(
    '($incidents_submitted + $incidents_review + $incidents_resolved) == 0',
    '($incidents_submitted + $incidents_review + $incidents_resolved + $incidents_rejected) == 0',
    $c
);

// Update Chart labels
$c = str_replace(
    "labels: dataEmptyIncidents ? ['No Incidents'] : ['Submitted', 'In Review', 'Resolved'],",
    "labels: dataEmptyIncidents ? ['No Incidents'] : ['Submitted', 'In Review', 'Resolved', 'Rejected'],",
    $c
);

// Update Chart dataset
$c = str_replace(
    "data: dataEmptyIncidents ? [1] : [<?php echo \$incidents_submitted; ?>, <?php echo \$incidents_review; ?>, <?php echo \$incidents_resolved; ?>],",
    "data: dataEmptyIncidents ? [1] : [<?php echo \$incidents_submitted; ?>, <?php echo \$incidents_review; ?>, <?php echo \$incidents_resolved; ?>, <?php echo \$incidents_rejected; ?>],",
    $c
);

// Update Chart colors
$c = str_replace(
    "backgroundColor: dataEmptyIncidents ? ['#e5e7eb'] : ['#ef4444', '#0ea5e9', '#10b981'], // Red, Blue, Green (Gray for empty)",
    "backgroundColor: dataEmptyIncidents ? ['#e5e7eb'] : ['#ef4444', '#0ea5e9', '#10b981', '#475569'],",
    $c
);

file_put_contents('dashboard.php', $c);
echo "fixed dashboard\n";
