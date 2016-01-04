<?php namespace Wubbajack\Encryption\Tests;

use Wubbajack\Encryption\EncryptedFile;

class EncryptedFileTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var EncryptedFile
     */
    protected $file;

    /**
     * Creates a test dummy file
     */
    protected function setUp()
    {
        $this->file = EncryptedFile::create(
            12345678,
            12345678,
            0,
            __FILE__
        );
    }

    /**
     * Tears the test down
     */
    protected function tearDown()
    {
        $this->file = null;
    }

    /**
     * Asserts the testing of creating a file
     */
    public function testCreate()
    {
        $this->assertInstanceOf(EncryptedFile::class, EncryptedFile::create(0, 0, 0, __FILE__));

        $this->setExpectedException(\RuntimeException::class);
        EncryptedFile::create(0, 0, 0, 'non-existing_file.doc');
    }

    /**
     * Tests the serialization and de-serialization of an EncryptedFile object
     */
    public function testSerializiation()
    {
        // Test whether serialization doesn't return empty
        $serialized = serialize($this->file);
        $this->assertNotEmpty($serialized);

        // Test whether serialization returned the proper object
        $file = unserialize($serialized);
        $this->assertInstanceOf(EncryptedFile::class, $file);

        // Test whether the objects are the same
        $this->assertEquals($this->file->getIv(), $file->getIv());
        $this->assertEquals($this->file->getChecksum(), $file->getChecksum());
        $this->assertEquals($this->file->getPadding(), $file->getPadding());
        $this->assertEquals($this->file->getFile(), $file->getFile());

        // Test whether the file property is still an instance of SplFileInfo
        $this->assertInstanceOf(\SplFileInfo::class, $file->getFile());
    }
}
