<?php

$key = openssl_random_pseudo_bytes(32); // 256-bit key
$encodedKey = base64_encode($key);
file_put_contents("secret.key", $encodedKey);
echo "<b>Key Generated and Stored successfully.";


$loadedKey = base64_decode(file_get_contents("secret.key"));
echo "<b>    </b><br><br>";


$plaintext = "This is a secret message.";
$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
$ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $loadedKey, 0, $iv);
echo "<b>Encrypted Message:</b> $ciphertext<br><br>";


$decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $loadedKey, 0, $iv);
echo "<b>Decrypted Message:</b> $decrypted<br><br>";


if (file_exists("secret.key")) {
    unlink("secret.key");
    echo "Key deleted successfully.<br>";
} else {
    echo "<b>Key file not found for deletion.</b><br>";
}
?>
