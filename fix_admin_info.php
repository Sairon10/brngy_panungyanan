<?php
$c = file_get_contents('admin/admin_info.php');

$search = '<div class="col-md-4">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-calendar"></i> Birthdate</div>';
$replace = '<div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-map-marker-alt"></i> Place of Birth</div>
                                        <?php if ($is_editing): ?><input type="text" name="birth_place" class="form-control" value="<?php echo htmlspecialchars($admin[\'birth_place\'] ?? \'\'); ?>" placeholder="e.g. Manila" required>
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin[\'birth_place\'] ?: \'N/A\'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-calendar"></i> Birthdate</div>';

$c = str_replace($search, $replace, $c);

// Update Sex and Civil Status to col-md-6
$c = str_replace(
    '<div class="col-md-4">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-venus-mars"></i> Sex</div>',
    '<div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-venus-mars"></i> Sex</div>',
    $c
);

$c = str_replace(
    '<div class="col-md-4">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-heart"></i> Civil Status</div>',
    '<div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-heart"></i> Civil Status</div>',
    $c
);

file_put_contents('admin/admin_info.php', $c);
echo "fixed admin info\n";
