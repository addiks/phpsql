<?php
/**
 * @author Gerrit Addiks <gerrit.addiks@brille24.de>
 */

namespace Addiks\PHPSQL\Filesystem;

use ErrorException;

class FileResourceProxy
{

    protected $resource;
    protected $mode;
    protected $index = 0;
    protected $isOpen = true;
    
    public function __construct($resource, $mode="a+")
    {

        if ($resource instanceof self) {
            $resource = $resource->getResource();
        }
        if (!is_resource($resource)) {
            throw new ErrorException("First parameter for FileResourceProxy has to be resource!");
        }

        $this->resource = $resource;
        $this->mode = $mode;
       
        # TODO: set index depending on mode
    }

    public function getResource()
    {
        return $this->resource;
    }

    protected function checkUsable()
    {
        if (!$this->isOpen) {
            throw new ErrorException("Filehandle is not open!");
        }
        if (!is_resource($this->resource)) {
            throw new ErrorException("File was deleted!");
        }
    }

    public function close()
    {
        $this->isOpen = false;
    }

    public function write($data)
    {
        $this->checkUsable();
        fseek($this->resource, $this->index, SEEK_SET);
        fwrite($this->resource, $data);
    }

    public function read($length)
    {
        $this->checkUsable();
        fseek($this->resource, $this->index, SEEK_SET);
        $data = fread($this->resource, $length);
        return $data;
    }

    public function truncate($size)
    {
        $this->checkUsable();
        ftruncate($this->resource, $size);
    }

    public function seek($offset, $seekMode)
    {
        $this->checkUsable();
        fseek($this->resource, $offset, $seekMode);
        $this->index = ftell($this->resource);
    }

    public function tell()
    {
        $this->checkUsable();
        return $this->index;
    }

    public function eof()
    {
        $this->checkUsable();
        fseek($this->resource, $this->index, SEEK_SET);
        return feof($this->resource);
    }

    public function lock($mode)
    {
        flock($this->resource, $mode);
    }

    public function readLine()
    {
        $this->checkUsable();
        fseek($this->resource, $this->index, SEEK_SET);
        return fgets($this->resource);
    }

    public function getData()
    {
        $seekBefore = $this->tell();
        $this->seek(0, SEEK_END);
        $fileSize = $this->tell();
        $this->seek(0, SEEK_SET);
        $data = $this->read($fileSize);
        $this->seek($seekBefore, SEEK_SET);
        return $data;
    }

    public function setData($data)
    {
        $this->seek(0, SEEK_SET);
        $this->truncate(0);
        $this->write($data);
    }

    public function addData($data)
    {
        $seekBefore = $this->tell();
        $this->seek(0, SEEK_END);
        $this->write($data);
        $this->seek($seekBefore, SEEK_SET);
    }

}
