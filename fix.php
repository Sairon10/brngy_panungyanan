<?php
$f = 'dashboard.php';
$c = file_get_contents($f);

// 1. Rename label and class for Submitted/Pending card, add href
$c = str_replace(
'<a href="incidents.php" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card danger mb-0"',
'<a href="incidents.php?status_filter=submitted" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card warning mb-0"',
$c);

// Also change the clock icon instead of file-signature, and Submitted to Pending
$c = str_replace(
'class="fas fa-file-signature"></i></div>
                                <div>
                                    <div class="stats-number"
                                        style="font-size: 2.25rem; font-weight: bold; line-height: 1; margin-bottom: 0.25rem;">
                                        <?php echo $incidents_submitted; ?></div>
                                    <div class="stats-label" style="font-size: 1rem; opacity: 0.9;">Submitted Incidents',
'class="fas fa-clock"></i></div>
                                <div>
                                    <div class="stats-number"
                                        style="font-size: 2.25rem; font-weight: bold; line-height: 1; margin-bottom: 0.25rem;">
                                        <?php echo $incidents_submitted; ?></div>
                                    <div class="stats-label" style="font-size: 1rem; opacity: 0.9;">Pending Incidents',
$c);

// 2. Change In Review href
$c = str_replace(
'<!-- In Review Card -->
                <div class="col-md-6">
                    <a href="incidents.php" class="text-decoration-none"',
'<!-- In Review Card -->
                <div class="col-md-6">
                    <a href="incidents.php?status_filter=in_review" class="text-decoration-none"',
$c);

// 3. Change Resolved href
$c = str_replace(
'<!-- Resolved Card -->
                <div class="col-md-6">
                    <a href="incidents.php" class="text-decoration-none"',
'<!-- Resolved Card -->
                <div class="col-md-6">
                    <a href="incidents.php?status_filter=resolved" class="text-decoration-none"',
$c);

// 4. Change Rejected href and color to danger
$c = str_replace(
'<!-- Rejected Card -->
                <div class="col-md-6">
                    <a href="incidents.php" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card mb-0"
                            style="margin: 0; border: none; background: linear-gradient(135deg, #475569 0%, #334155 100%);',
'<!-- Rejected Card -->
                <div class="col-md-6">
                    <a href="incidents.php?status_filter=closed" class="text-decoration-none"
                        style="color: inherit; display: block; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                        <div class="admin-stats-card danger mb-0"
                            style="margin: 0; border: none;',
$c);

// 5. Update chart labels and colors
$c = str_replace(
"labels: dataEmptyIncidents ? ['No Incidents'] : ['Submitted', 'In Review', 'Resolved', 'Rejected'],",
"labels: dataEmptyIncidents ? ['No Incidents'] : ['Pending', 'In Review', 'Resolved', 'Rejected'],",
$c);

$c = str_replace(
"backgroundColor: dataEmptyIncidents ? ['#e5e7eb'] : ['#ef4444', '#0ea5e9', '#10b981', '#475569'],",
"backgroundColor: dataEmptyIncidents ? ['#e5e7eb'] : ['#f59e0b', '#0ea5e9', '#10b981', '#ef4444'],",
$c);

file_put_contents($f, $c);
echo 'Done';
