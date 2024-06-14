<?php
// Copyright 2022 The Ip2Region Authors. All rights reserved.
// Use of this source code is governed by a Apache2.0-style
// license that can be found in the LICENSE file.
//
// @Author Lion <chenxin619315@gmail.com>
// @Date   2022/06/21

if (!defined('ABSPATH')) {
    exit;
}

class Ip2region
{
    const HeaderInfoLength = 256;
    const VectorIndexRows  = 256;
    const VectorIndexCols  = 256;
    const VectorIndexSize  = 8;
    const SegmentIndexSize = 14;

    // Default xdb file path
    const XDBFile = __DIR__ . '/ip2region.xdb';

    // xdb file handle
    private $handle = null;

    // xdb content buffer
    private $contentBuff = null;

    // ---
    // static function to create searcher

    /**
     * @throws Exception
     */
    public static function newWithFileOnly()
    {
        return new Ip2region();
    }

    /**
     * @throws Exception
     */
    public static function newWithBuffer($cBuff)
    {
        return new Ip2region($cBuff);
    }

    // --- End of static creator

    /**
     * initialize the xdb searcher
     * @throws Exception
     */
    function __construct($cBuff = null)
    {
        // check the content buffer first
        if ($cBuff != null) {
            $this->contentBuff = $cBuff;
        } else {
            // open the xdb binary file
            $this->handle = fopen(self::XDBFile, "r");
            if ($this->handle === false) {
                throw new Exception(sprintf("failed to open xdb file '%s'", self::XDBFile));
            }
        }
    }

    function close()
    {
        if ($this->handle != null) {
            fclose($this->handle);
        }
    }

    /**
     * find the region info for the specified ip address
     * @throws Exception
     */
    function search($ip)
    {
        // check and convert the string ip to a 4-bytes long
        if (is_string($ip)) {
            $ip = self::ip2long($ip);
            if ($ip === null) {
                throw new Exception("invalid ip address `$ip`");
            }
        }

        // locate the segment index block based on the vector index
        $il0 = ($ip >> 24) & 0xFF;
        $il1 = ($ip >> 16) & 0xFF;
        $idx = $il0 * self::VectorIndexCols * self::VectorIndexSize + $il1 * self::VectorIndexSize;
        if ($this->contentBuff != null) {
            $sPtr = self::getLong($this->contentBuff, self::HeaderInfoLength + $idx);
            $ePtr = self::getLong($this->contentBuff, self::HeaderInfoLength + $idx + 4);
        } else {
            // read the vector index block
            $buff = $this->read(self::HeaderInfoLength + $idx, 8);
            if ($buff === null) {
                throw new Exception("failed to read vector index at {$idx}");
            }

            $sPtr = self::getLong($buff, 0);
            $ePtr = self::getLong($buff, 4);
        }

        // binary search the segment index to get the region info
        $dataLen = 0;
        $dataPtr = null;
        $l = 0;
        $h = ($ePtr - $sPtr) / self::SegmentIndexSize;
        while ($l <= $h) {
            $m = ($l + $h) >> 1;
            $p = $sPtr + $m * self::SegmentIndexSize;

            // read the segment index
            $buff = $this->read($p, self::SegmentIndexSize);
            if ($buff == null) {
                throw new Exception("failed to read segment index at {$p}");
            }

            $sip = self::getLong($buff, 0);
            if ($ip < $sip) {
                $h = $m - 1;
            } else {
                $eip = self::getLong($buff, 4);
                if ($ip > $eip) {
                    $l = $m + 1;
                } else {
                    $dataLen = self::getShort($buff, 8);
                    $dataPtr = self::getLong($buff, 10);
                    break;
                }
            }
        }

        // match nothing interception.
        if ($dataPtr == null) {
            return null;
        }

        // load and return the region data
        $buff = $this->read($dataPtr, $dataLen);
        if ($buff == null) {
            return null;
        }

        return $buff;
    }

    // read specified bytes from the specified index
    private function read($offset, $len)
    {
        // check the in-memory buffer first
        if ($this->contentBuff != null) {
            return substr($this->contentBuff, $offset, $len);
        }

        // read from the file
        $r = fseek($this->handle, $offset);
        if ($r == -1) {
            return null;
        }

        $buff = fread($this->handle, $len);
        if ($buff === false || strlen($buff) != $len) {
            return null;
        }

        return $buff;
    }

    // --- static util functions ----

    // convert a string ip to long
    public static function ip2long($ip)
    {
        $ip = ip2long($ip);
        if ($ip === false) {
            return null;
        }

        // convert signed int to unsigned int if on 32 bit operating system
        if ($ip < 0 && PHP_INT_SIZE == 4) {
            $ip = sprintf("%u", $ip);
        }

        return $ip;
    }

    // read a 4bytes long from a byte buffer
    public static function getLong($b, $idx)
    {
        $val = (ord($b[$idx])) | (ord($b[$idx + 1]) << 8)
            | (ord($b[$idx + 2]) << 16) | (ord($b[$idx + 3]) << 24);

        // convert signed int to unsigned int if on 32 bit operating system
        if ($val < 0 && PHP_INT_SIZE == 4) {
            $val = sprintf("%u", $val);
        }

        return $val;
    }

    // read a 2bytes short from a byte buffer
    public static function getShort($b, $idx)
    {
        return ((ord($b[$idx])) | (ord($b[$idx + 1]) << 8));
    }

    // load the xdb content from a file handle
    public static function loadContent($handle)
    {
        if (fseek($handle, 0, SEEK_END) == -1) {
            return null;
        }

        $size = ftell($handle);
        if ($size === false) {
            return null;
        }

        // seek to the head for reading
        if (fseek($handle, 0) == -1) {
            return null;
        }

        $buff = fread($handle, $size);
        if ($buff === false || strlen($buff) != $size) {
            return null;
        }

        return $buff;
    }

    // load the xdb content from the default file path
    public static function loadContentFromFile()
    {
        $str = file_get_contents(self::XDBFile, false);
        if ($str === false) {
            return null;
        } else {
            return $str;
        }
    }
}
