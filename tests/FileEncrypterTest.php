<?php namespace Wubbajack\Encryption\Tests;

use Wubbajack\Encryption\EncryptedFile;
use Wubbajack\Encryption\FileEncrypter;

use Wubbajack\Encryption\Exceptions\EncryptException;
use Wubbajack\Encryption\Exceptions\DecryptException;

class FileEncrypterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var FileEncrypter
     */
    protected $fileCrypt;

    /**
     * @var string
     */
    protected $default_key = '2ApWknyPwb4N8Hhv4zT34FvubrCTx0Sh';

    /**
     * @var string
     */
    protected $default_cipher = MCRYPT_RIJNDAEL_128;

    /**
     * @var string
     */
    protected $default_mode = MCRYPT_MODE_CBC;

    /**
     * @var string
     */
    protected $test_file;

    /**
     * @var string
     */
    protected $test_encrypted_file;

    /**
     * @var string
     */
    protected $test_decrypted_file;

    /**
     * Sets up the file encrypter
     */
    protected function setUp()
    {
        $this->fileCrypt = new FileEncrypter($this->default_key);
        $this->test_file = $this->createTestFile();
        $this->test_encrypted_file = __DIR__ .'/test.enc';
        $this->test_decrypted_file = __DIR__ .'/test.dec.json';
    }

    protected function tearDown()
    {
        $this->fileCrypt->setMode($this->default_mode);
        $this->fileCrypt->setCipher($this->default_cipher);
        $this->fileCrypt->setKey($this->default_key);

        $this->cleanupFiles();
    }

    /**
     * Tests whether the cipher is properly set and the block size is updated correctly
     */
    public function testSetCipher()
    {
        $cipher = MCRYPT_TRIPLEDES;
        $this->fileCrypt->setCipher($cipher);

        $this->assertEquals($cipher, $this->readAttribute($this->fileCrypt, 'cipher'));

        $block_size = mcrypt_get_block_size($cipher, $this->readAttribute($this->fileCrypt, 'mode'));
        $this->assertEquals($block_size, $this->readAttribute($this->fileCrypt, 'block'));
    }

    /**
     * Tests whether the mode is set correctly and and the block size is updated accordingly
     */
    public function testSetMode()
    {
        $mode = MCRYPT_MODE_OFB;
        $this->fileCrypt->setMode($mode);

        $this->assertEquals($mode, $this->readAttribute($this->fileCrypt, 'mode'));

        $block_size = mcrypt_get_block_size($this->readAttribute($this->fileCrypt, 'cipher'), $mode);
        $this->assertEquals($block_size, $this->readAttribute($this->fileCrypt, 'block'));
    }

    /**
     * Tests the setting of the key
     */
    public function testSetKey()
    {
        $key = bin2hex(openssl_random_pseudo_bytes(16));
        $this->fileCrypt->setKey($key);

        $this->assertEquals($key, $this->readAttribute($this->fileCrypt, 'key'));
    }

    /**
     * Test encryption
     *
     * @throws \Wubbajack\Encryption\Exceptions\EncryptException
     */
    public function testEncrypt()
    {
        $this->assertInstanceOf(
            EncryptedFile::class,
            $this->fileCrypt->encrypt($this->test_file, $this->test_encrypted_file)
        );

        // Try testing with a non-existent source file
        $this->setExpectedException(EncryptException::class);
        $this->fileCrypt->encrypt(__DIR__ .'/test.test', __DIR__ .'/test.test.enc');
    }

    /**
     * Test decryption
     */
    public function testDecrypt()
    {
        $encryptedFile = $this->fileCrypt->encrypt($this->test_file, $this->test_encrypted_file);

        $this->fileCrypt->decrypt($encryptedFile, $this->test_decrypted_file);

        // Test if the decrypted file exists
        $this->assertTrue(file_exists($this->test_decrypted_file));

        // Test if the checksum equals the original file
        $this->assertEquals($encryptedFile->getChecksum(), sha1_file($this->test_decrypted_file));

        // Test if the decrypted file contains the same content as the original
        $this->assertEquals(file_get_contents($this->test_file), file_get_contents($this->test_decrypted_file));

        // Test decryption of encrypted file with incorrect wrong IV
        try {
            $invalidEncryptedFile = EncryptedFile::create(
                '2394qsf3-f9',
                $encryptedFile->getChecksum(),
                $encryptedFile->getPadding(),
                $encryptedFile->getFile()->getRealPath()
            );
            $this->fileCrypt->decrypt($invalidEncryptedFile, $this->test_decrypted_file);
            $this->fail('No exception was thrown on decrypting with an incorrect IV');
        } catch (\Exception $e) {
            $this->assertInstanceOf(
                DecryptException::class,
                $e,
                'Expected an instance of DecryptException containing a message about IV, got '. get_class($e)
            );
        }

        // Test decryption of encrypted file with incorrect checksum
        try {
            $invalidEncryptedFile = EncryptedFile::create(
                $encryptedFile->getIV(),
                bin2hex(openssl_random_pseudo_bytes(16)),
                $encryptedFile->getPadding(),
                $encryptedFile->getFile()->getRealPath()
            );
            $this->fileCrypt->decrypt($invalidEncryptedFile, $this->test_decrypted_file);
            $this->fail('No exception was thrown on decrypting with unmatching checksums');
        } catch (\Exception $e) {
            $this->assertInstanceOf(
                DecryptException::class,
                $e,
                'Expected an instance of DecryptException with a message about the checksum, got '. get_class($e)
            );
        }

        // Test decryption of encrypted file with twice the amount of padding
        try {
            $invalidEncryptedFile = EncryptedFile::create(
                $encryptedFile->getIV(),
                $encryptedFile->getChecksum(),
                $encryptedFile->getPadding() * 2,
                $encryptedFile->getFile()->getRealPath()
            );
            $this->fileCrypt->decrypt($invalidEncryptedFile, $this->test_decrypted_file);
            $this->fail('No exception was thrown on decrypting with too much padding');
        } catch (\Exception $e) {
            $this->assertInstanceOf(
                DecryptException::class,
                $e,
                'Expected an instance of DecryptException with a message about the checksum, got '. get_class($e)
            );
        }
    }

    /**
     * Test streaming decryption
     */
    public function testStreamDecrypt()
    {
        $encryptedFile = $this->fileCrypt->encrypt($this->test_file, $this->test_encrypted_file);

        // Test whether the checksum of the decrypted data equals the checksum of the file
        $decrypted_data = '';
        $this->fileCrypt->streamDecrypt(
            $encryptedFile,
            function ($data, $stream) use (&$decrypted_data, $encryptedFile) {
                $decrypted_data .= $data;
            }
        );

        $this->assertEquals(sha1($decrypted_data), $encryptedFile->getChecksum());
    }

    /**
     * Tests encrypt exception
     *
     * @throws EncryptException
     */
    public function testEncryptException()
    {
        $this->setExpectedException(EncryptException::class);
        $this->fileCrypt->encrypt(__DIR__ .'non-existant.doc', $this->test_encrypted_file);
    }

    /**
     * Removes all of the created encrypted and decrypted files created during testing
     */
    protected function cleanupFiles()
    {
        if (is_file($this->test_file)) {
            unlink($this->test_file);
        }

        if (is_file($this->test_encrypted_file)) {
            unlink($this->test_encrypted_file);
        }

        if (is_file($this->test_decrypted_file)) {
            unlink($this->test_decrypted_file);
        }
    }

    /**
     * Creates a test file
     *
     * @return string
     */
    protected function createTestFile()
    {
        $file = __DIR__ .'/test.json';

        file_put_contents($file, json_encode(['unit' => 'test']));
        return $file;
    }
}
