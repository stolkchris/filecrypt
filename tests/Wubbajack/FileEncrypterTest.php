<?php namespace Wubbajack\Tests;

use Wubbajack\Encryption\EncryptedFile;
use Wubbajack\Encryption\FileEncrypter;

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
    }

    /**
     * Test decryption
     */
    public function testDecrypt()
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    /**
     * Test streaming decryption
     */
    public function testStreamDecrypt()
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    /**
     * Removes all of the created encrypted and decrypted files created during testing
     */
    protected function cleanupFiles()
    {
        if (is_file($this->test_file)) {
            unlink($this->test_file);
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
