<?php namespace Wubbajack\Encryption;

/**
 * Encrypted File class
 *
 * Contains information about an encrypted file:
 *  - The IV used for encryption
 *  - The amount of padding encryption has added to the file
 *  - A SHA1 checksum of the original file
 *  - A reference to the encrypted file, using \SplFileInfo
 *
 * @author  Chris Stolk <stolkchris@gmail.com>
 * @package Wubbajack\Encryption
 * @license MIT <https://opensource.org/licenses/MIT>
 * @since   0.0.1
 */
class EncryptedFile implements \Serializable
{
    /**
     * @var string
     */
    protected $iv;

    /**
     * @var string
     */
    protected $checksum;

    /**
     * @var \SplFileInfo
     */
    protected $file;

    /**
     * @var int
     */
    protected $padding = 0;

    /**
     * EncryptedFile constructor.
     *
     * @param string       $iv
     * @param string       $checksum
     * @param \SplFileInfo $file
     */
    public function __construct($iv, $checksum, \SplFileInfo $file)
    {
        $this->iv       = $iv;
        $this->file     = $file;
        $this->checksum = $checksum;
    }

    /**
     * Creates a new encrypted file object
     *
     * @param string              $iv
     * @param string              $checksum
     * @param int                 $padding
     * @param \SplFileInfo|string $file
     *
     * @return static
     */
    public static function create($iv, $checksum, $padding, $file)
    {
        if (!($file instanceof \SplFileInfo)) {
            $file = new \SplFileInfo($file);
        }

        // Returns the encrypted file
        $encryptedFile = new static($iv, $checksum, $file);
        return $encryptedFile->setPadding($padding);
    }

    /**
     * Returns the IV
     *
     * @return string
     */
    public function getIv()
    {
        return $this->iv;
    }

    /**
     * Returns the checksum of the encrypted file
     *
     * @return string
     */
    public function getChecksum()
    {
        return $this->checksum;
    }

    /**
     * Returns the encrypted file
     *
     * @return \SplFileInfo
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Returns the amount of bytes that have been padded
     *
     * @return int
     */
    public function getPadding()
    {
        return $this->padding;
    }

    /**
     * Sets the padding
     *
     * @param int$padding
     * @return $this
     */
    public function setPadding($padding)
    {
        $this->padding = (int)$padding;

        return $this;
    }

    /**
     * String representation of object
     *
     * @link  http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize([
            'iv'       => $this->iv,
            'checksum' => $this->checksum,
            'padding'  => $this->padding,
            'file'     => $this->file instanceof \SplFileInfo ? $this->file->getRealPath() : null
        ]);
    }

    /**
     * Constructs the object
     *
     * @link  http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     *                           The string representation of the object.
     *                           </p>
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);

        $this->iv       = $data['iv'];
        $this->checksum = $data['checksum'];
        $this->padding  = $data['padding'];

        if ($data['file']) {
            $this->file = new \SplFileInfo($data['file']);
        }
    }
}
