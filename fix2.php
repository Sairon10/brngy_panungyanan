<?php
$c = file_get_contents('family_member_profile.php');
$target = '                            <input type="text" name="philsys_card_no" class="form-control"
                                value="<?php echo htmlspecialchars($data[\'philsys_card_no\'] ?? \'\'); ?>"
                                placeholder="e.g. 09123456789" required>';
$replacement = '                            <input type="text" name="philsys_card_no" class="form-control"
                                value="<?php echo htmlspecialchars($data[\'philsys_card_no\'] ?? \'\'); ?>"
                                placeholder="e.g. 09123456789">';
$c = str_replace($target, $replacement, $c);
file_put_contents('family_member_profile.php', $c);
echo "fixed\n";
