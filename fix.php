<?php
$c = file_get_contents('family_member_profile.php');
$c = preg_replace('/name="philsys_card_no" class="form-control"([^>]+)required/s', 'name="philsys_card_no" class="form-control"$1', $c);
file_put_contents('family_member_profile.php', $c);
echo "fixed\n";
