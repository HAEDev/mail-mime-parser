<?php
namespace ZBateson\MailMimeParser;

/**
 * Implementation of a stream wrapper representing content for a specific MIME
 * part of an email message.
 * 
 * Basically defines character boundaries for a "parent" stream - namely the
 * main stream for a message - where read operations are not permitted beyond
 * the character boundaries of a specific part.  The boundaries are parsed from
 * parameters passed as the "path" to stream_open (with fopen, etc...)
 * 
 * Note that only read operations are permitted.
 *
 * @author Zaahid Bateson
 */
class PartStream
{
    /**
     * The protocol name used to register the stream with
     * stream_wrapper_register
     */
    const STREAM_WRAPPER_PROTOCOL = 'mmp-mime-message';
    
    /**
     * @var resource The resource handle for the opened part.  Essentially the
     *      MIME message's stream handle.
     */
    protected $handle;
    
    /**
     * @var int The offset character position in $this->handle where the current
     *      mime part's content starts.
     */
    protected $start;
    
    /**
     * @var int The offset character position in $this->handle where the current
     *      mime part's content ends.
     */
    protected $end;
    
    /**
     * @var \ZBateson\MailMimeParser\PartStreamRegistry The registry service
     *      object. 
     */
    protected $registry;
    
    /**
     * @var int the current read position.
     */
    private $position;
    
    /**
     * Constructs a PartStream.
     */
    public function __construct()
    {
        $di = SimpleDi::singleton();
        $this->registry = $di->getPartStreamRegistry();
    }
    
    /**
     * Called in response to fopen, file_get_contents, etc... with a
     * PartStream::STREAM_WRAPPER_PROTOCOL, e.g.,
     * fopen('mmp-mime-message://...');
     * 
     * The \ZBateson\MailMimeParser\Message object ID must be passed as the
     * 'host' part in $path.  The start and end boundaries of the part must be
     * passed as query string parameters in the path, for example:
     * 
     * fopen('mmp-mime-message://123456?start=0&end=20');
     * 
     * This would open a file handle to a MIME message with the ID 123456, with
     * a start offset of 0, and an end offset of 20.
     * 
     * TODO: $mode is not validated, although only read operations are
     * implemented in PartStream.  $options are not checked for error reporting
     * mode.
     * 
     * @param string $path The requested path
     * @param string $mode The requested open mode
     * @param int $options Additional streams API flags
     * @param string $opened_path The full path of the opened resource
     * @return boolean true if the resource was opened successfully
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $parts = parse_url($path);
        if (empty($parts['host']) || empty($parts['query'])) {
            return false;
        }
        
        $vars = [];
        parse_str($parts['query'], $vars);
        
        $id = $parts['host'];
        $handle = $this->registry->get($id);
        if ($handle === null || !isset($vars['start']) || !isset($vars['end'])) {
            return false;
        }
        
        $this->position = 0;
        $this->handle = $handle;
        $this->start = $vars['start'];
        $this->end = $vars['end'];
        return true;
    }
    
    /**
     * Reads up to $count characters from the stream and returns them.
     * 
     * @param int $count
     * @return string
     */
    public function stream_read($count)
    {
        $pos = ftell($this->handle);
        fseek($this->handle, $this->start + $this->position);
        $max = $this->end - ($this->start + $this->position);
        $nRead = min($count, $max);
        $ret = '';
        if ($nRead > 0) {
            $ret = fread($this->handle, $nRead);
        }
        $this->position += strlen($ret);
        fseek($this->handle, $pos);
        return $ret;
    }
    
    /**
     * Returns the current read position.
     * 
     * @return int
     */
    public function stream_tell()
    {
        return $this->position;
    }
    
    /**
     * Returns true if the end of the stream has been reached.
     * 
     * @return boolean
     */
    public function stream_eof()
    {
        if (feof($this->handle) || $this->position + $this->start >= $this->end) {
            return true;
        }
        return false;
    }
    
    /**
     * Moves the pointer to the given offset, in accordance to $whence.
     * 
     * @param int $offset
     * @param int $whence One of SEEK_SET, SEEK_CUR and SEEK_END.
     * @return boolean
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        switch ($whence) {
            case SEEK_SET:
                if ($offset + $this->start < $this->end && $offset >= 0) {
                    $this->position = $offset;
                    return true;
                } else {
                    return false;
                }
                break;
            case SEEK_CUR:
                if ($offset >= 0) {
                     $this->position += $offset;
                     return true;
                } else {
                     return false;
                }
                break;
            case SEEK_END:
                if ($this->end + $offset >= $this->start) {
                     $this->position = ($this->end - $this->start) + $offset;
                     return true;
                } else {
                     return false;
                }
                break;
            default:
                return false;
        }
    }
    
    /**
     * Returns information about the opened stream, as would be expected by
     * fstat.
     * 
     * @return array
     */
    public function stream_stat()
    {
        $arr = fstat($this->handle);
        if (!empty($arr['size'])) {
            $arr['size'] = $this->end - $this->start;
        }
        return $arr;
    }
}