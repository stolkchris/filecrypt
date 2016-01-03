# Filecrypt
A PHP package for encrypting and decrypting files on the go!

[![Build Status][ico-travis]][link-travis]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Software License][ico-license]](LICENSE.md)

## DISCLAIMER
This package is still a work in progress. This means that Method signatures and return values will change over time. Implementation specifics will also change.

If you do wish to use or test this package, do so with care. Everything has been "humanly" tested, still working on writing proper tests.

If you wish to contribute please start of with creating new issues before sending in pull requests.

## Info
The goal of this project is to offer a nice way of working with encrypted files in PHP. There are some great (shell) tools out there that can do the same thing. But when decrypting streams becomes necessary, I've found it to be more and more difficult to implement properly.

This package uses stream filters for encryption and decryption, using box standard mcrypt ciphers. This allows for streaming decryption (and in the future encryption).

### Installation and Requirements
You can install the package easily using composer
```bash
$ composer require wubbajack/filecrypt
```

The minimum requirements are:
 - PHP 5.6
 - Mcrypt

### Usage
Below are some examples on how to use the FileEncrypter class

#### Encrypting files
```php
<?php

/**
 * This creates a new instance of the FileEncrypter. By default
 * it uses RIJNDAEL-128 with a 16 bit block size, which corresponds with the AES standard.
 * Please have a look at the class comments to see why this decision was made
 */
$fileEncrypter = new Wubbajack\Encryption\FileEncrypter($key);
$source_file   = '/path/to/source/file.jpg';
$target_file   = '/path/to/encryted/file.enc';

/**
 * Encrypts a source file to a target file and returns an EncryptedFile instance
 */
$encryptedFile = $fileEncrypter->encrypt($source_file, $target_file);
```

#### Decrypting files
```php
<?php

/**
 * In this example we assume that we already have an EncryptedFile instance
 * where we can extract the required information from
 */
$fileCrypt   = new Wubbajack\Encryption\FileEncrypter($key);
$target_file = '/path/to/decrypted/file.jpg';

// Decrypts our encrypted file and returns the path to the file
$fileCrypt->decrypt($encryptedFile, $target_file);
```

#### Streaming decryption
```php
<?php

/**
 * In this example we also assume that we already have an EncryptedFile instance
 */
$fileCrypt = new Wubbajack\Encryption\FileEncrypter($key);

/**
 * The streamDecrypt method allows you to supply a callback and manipulate or echo the data.
 * This can be very useful when streaming encrypted media back to a client.
 */
$fileCrypt->streamDecrypt($encryptedFile, function ($data, $stream) {
    echo $data;

    if (feof($stream)) {
        // I have finished!
    }
});
```

## TODO
 - Write proper documentation
 - Add the possibility of writing custom data to an encrypted stream and save it afterwards

 [ico-version]: https://img.shields.io/packagist/v/wubbajack/filecrypt.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/wubbajack/filecrypt/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/wubbajack/filecrypt.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/wubbajack/filecrypt.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/wubbajack/filecrypt.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/wubbajack/filecrypt
[link-travis]: https://travis-ci.org/wubbajack/filecrypt
[link-scrutinizer]: https://scrutinizer-ci.com/g/wubbajack/filecrypt/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/wubbajack/filecrypt
[link-downloads]: https://packagist.org/packages/wubbajack/filecrypt
[link-author]: https://github.com/:author_username
[link-contributors]: ../../contributors
