<?php namespace Wubbajack\Encryption;

use Wubbajack\Encryption\Exceptions\EncryptException;
use Wubbajack\Encryption\Exceptions\DecryptException;

/**
 * File Encryptor class.
 *
 * The file encryptor class by default encrypts using the AES standard.
 * Please note that it says RIJNDAEL_128 which refers to the block size and not the key size.
 * Refer to the following URL's for more details on this
 * https://www.leaseweb.com/labs/2014/02/aes-php-mcrypt-key-padding/
 * http://stackoverflow.com/questions/6770370/aes-256-encryption-in-php
 *
 *
 * @author  Chris Stolk <stolkchris@gmail.com>
 * @package Wubbajack\Encryption
 * @license MIT <https://opensource.org/licenses/MIT>
 * @since   0.0.1
 */
class FileEncrypter
{

    const CHUNK_BYTES = 8192;

    /**
     * The encryption key.
     *
     * @var string
     */
    protected $key;

    /**
     * The algorithm used for encryption.
     *
     * @var string
     */
    protected $cipher = MCRYPT_RIJNDAEL_128;

    /**
     * The mode used for encryption.
     *
     * @var string
     */
    protected $mode = MCRYPT_MODE_CBC;

    /**
     * The block size of the cipher.
     *
     * @var int
     */
    protected $block = 16;

    /**
     * FileEncrypter constructor.
     *
     * @param $key
     */
    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * Set the encryption key.
     *
     * @param  string  $key
     * @return void
     */
    public function setKey($key)
    {
        $this->key = (string) $key;
    }

    /**
     * Set the encryption cipher.
     *
     * @param  string  $cipher
     * @return $this
     */
    public function setCipher($cipher)
    {
        $this->cipher = $cipher;
        $this->updateBlockSize();

        return $this;
    }

    /**
     * Set the encryption mode.
     *
     * @param  string $mode
     * @return $this
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
        $this->updateBlockSize();

        return $this;
    }

    /**
     * Encrypts a file and returns the checksum of the encrypted file.
     * You can use the checksum to verify integrity as this method of encryption (symmetrical)
     * doesn't allow for easy integrity verification.
     *
     * It's not required but highly recommended as an attacker can shift bytes and thus changes the data
     * on the encrypted file.
     *
     * @param string $source
     * @param string $target
     * @return EncryptedFile An encrypted file object containing information about the IV, checksum and padding
     * @throws EncryptException
     */
    public function encrypt($source, $target)
    {
        $iv = mcrypt_create_iv($this->getIvSize(), $this->getRandomizer());

        try {
            $this->encryptFile($source, $target, $iv);
        } catch (\Exception $e) {
            throw new EncryptException('Unable to encrypt file', 0, $e);
        }

        // Returns the encrypted file object, sets the padding and the source file checksum for later checking
        return EncryptedFile::create(
            $iv,
            $this->calculateChecksum($source),
            $this->calculatePadding($source, $target),
            $target
        );
    }

    /**
     * Decrypts the source file to a target file. The checksum is an optional parameter
     * that can be used to verify integrity of the file some ciphers offer no integrity check of their own.
     *
     * It's an optional parameter but be warned, the file may have been tampered with by an attacker.
     *
     * @param EncryptedFile $encryptedFile
     * @param string        $target
     * @return string Path to the target file
     * @throws DecryptException
     */
    public function decrypt(EncryptedFile $encryptedFile, $target)
    {
        // Get the path to the source file
        $source = $encryptedFile->getFile()->getRealPath();

        try {
            $this->decryptFile($source, $target, $encryptedFile->getIv(), $encryptedFile->getPadding());
        } catch (\Exception $e) {
            throw new DecryptException('Unable to decrypt file', 0, $e);
        }

        // Verify the integrity of the decrypted file checking the checksum against the checksum of the original source file
        if (!$this->verifyChecksum($target, $encryptedFile->getChecksum())) {
            unlink($target);
            throw new DecryptException('Invalid checksum on decrypted file');
        }

        return $target;
    }

    /**
     * Decrypts a file in a stream, performing the callback on each successive decrypted block.
     * If the checksum is provided it checks it against the encrypted file for integrity.
     *
     * The callback can accept two arguments:
     *  - $data   - A chunk of decrypted data
     *  - $stream - The resource stream that is decrypting
     *
     * @param EncryptedFile $encryptedFile
     * @param \Closure      $callback
     * @throws DecryptException
     */
    public function streamDecrypt(EncryptedFile $encryptedFile, \Closure $callback)
    {
        // Get the path to the encrypted file
        $source = $encryptedFile->getFile()->getRealPath();

        // Check if callback is a closure
        if (!($callback instanceof \Closure)) {
            throw new DecryptException('Callback must be callable');
        }

        // Get the decryption stream
        $stream = $this->createDecryptionStream($source, $this->getOptions($encryptedFile->getIv()));

        // Run the callback while the file pointer isn't at the end
        while (!feof($stream)) {
            $callback(fread($stream, self::CHUNK_BYTES), $stream);
        }
    }

    /**
     * Encrypts the file
     *
     * @param string $source_file
     * @param string $target_file
     * @param string $iv
     *
     * @return void
     */
    protected function encryptFile($source_file, $target_file, $iv)
    {
        // We start by setting up both the source and target streams
        // and applying all the necessary stream filters for encryption.

        $source  = fopen($source_file, 'r');
        $target  = $this->createEncryptionStream($target_file, $this->getOptions($iv));

        // We copy the source into the target stream, passing through the encryption filter
        $this->copyStream($source, $target);

        // Close the source file and target files
        fclose($source);
        fclose($target);
    }

    /**
     * Decrypts a source file into a target file
     *
     * @param string $source
     * @param string $target
     * @param string $iv
     * @param int    $padding
     *
     * @return void
     */
    protected function decryptFile($source, $target, $iv, $padding = 0)
    {
        // We create a stream with a decryption filter appended to it
        $source = $this->createDecryptionStream($source, $this->getOptions($iv));
        $target = fopen($target, 'w+');

        // We copy the source into the target, decrypting it in the process
        $this->copyStream($source, $target, $padding);

        // Close both source and target
        fclose($source);
        fclose($target);
    }

    /**
     * Creates a stream that encrypts when written to
     *
     * @param string $target
     * @param array  $options
     *
     * @return resource
     * @throws EncryptException
     */
    protected function createEncryptionStream($target, array $options)
    {
        // Open up the resources to both the source and target
        $stream = fopen($target, 'w+');

        // Append the stream write filter with the correct encryption cipher
        stream_filter_append($stream, $this->getEncryptionFilterName(), STREAM_FILTER_WRITE, $options);

        // Returns the target stream
        return $stream;
    }

    /**
     * Creates a stream that is decrypted when read from
     *
     * @param string $source
     * @param array  $options
     *
     * @return resource
     */
    protected function createDecryptionStream($source, array $options)
    {
        $stream = fopen($source, 'rb');

        // Append the stream read filter with the decryption cipher
        stream_filter_append($stream, $this->getDecryptionFilterName(), STREAM_FILTER_READ, $options);

        // Returns the target stream
        return $stream;
    }

    /**
     * Copies a source stream to a target stream.
     * If the padding parameter is set it will remove said amount of bytes from the end of the file.
     *
     * This method does not use stream_copy_to_stream on purpose because this way we have more control
     * over the process of moving data from one stream to another.
     *
     * @param resource $source
     * @param resource $target
     * @param null|int $padding
     *
     * @return void
     */
    protected function copyStream($source, $target, $padding = null)
    {
        // Ensure that both pointers are at the start of the file
        while (!feof($source)) {
            $data = fread($source, self::CHUNK_BYTES);

            // If eof is reached and padding is set, remove it from the file
            if (feof($source) && $padding) {
                $data = substr($data, 0, -$padding);
            }

            fwrite($target, $data);
        }
    }

    /**
     * Returns the options for the stream filter
     * @param null $iv If no IV is set, one will be created
     *
     * @return array Returns an array with 'mode','key' and 'iv'
     */
    protected function getOptions($iv = null)
    {
        if ($iv === null) {
            mcrypt_create_iv($this->getIvSize(), $this->getRandomizer());
        }

        return [
            'mode' => $this->mode,
            'key'  => $this->key,
            'iv'   => $iv,
        ];
    }

    /**
     * Get the IV size for the cipher.
     *
     * @return int
     */
    protected function getIvSize()
    {
        return mcrypt_get_iv_size($this->cipher, $this->mode);
    }

    /**
     * Returns the encryption cipher for the stream filter
     *
     * @return string
     */
    protected function getEncryptionFilterName()
    {
        return 'mcrypt.'. $this->cipher;
    }

    /**
     * Returns the decryption cipher for the stream filter
     *
     * @return string
     */
    protected function getDecryptionFilterName()
    {
        return 'mdecrypt.'. $this->cipher;
    }

    /**
     * Get the random data source available for the OS.
     *
     * @return int
     */
    protected function getRandomizer()
    {
        if (defined('MCRYPT_DEV_URANDOM')) {
            return MCRYPT_DEV_URANDOM;
        }

        if (defined('MCRYPT_DEV_RANDOM')) {
            return MCRYPT_DEV_RANDOM;
        }

        mt_srand();

        return MCRYPT_RAND;
    }

    /**
     * Compares the given checksum with the actual file checksum.
     * Returns true if they match, false if not
     *
     * @param string $file
     * @param string $checksum
     * @return bool
     */
    protected function verifyChecksum($file, $checksum)
    {
        return strcmp($checksum, $this->calculateChecksum($file)) === 0;
    }

    /**
     * Update the block size for the current cipher and mode.
     *
     * @return void
     */
    protected function updateBlockSize()
    {
        $this->block = mcrypt_get_iv_size($this->cipher, $this->mode);
    }

    /**
     * Calculates the padding that was added during encryption
     *
     * @param string $source Path the the source file
     * @param string $target Path to the target file
     * @return int
     */
    protected function calculatePadding($source, $target)
    {
        return filesize($target) - filesize($source);
    }

    /**
     * Calculates the checksum of the file
     *
     * @param string $file
     * @return string
     */
    protected function calculateChecksum($file)
    {
        return sha1_file($file);
    }
}
