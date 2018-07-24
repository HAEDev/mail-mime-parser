<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\MailMimeParser;

use Psr\Http\Message\StreamInterface;
use ZBateson\MailMimeParser\Header\HeaderFactory;
use ZBateson\MailMimeParser\Message\Part\MimePart;
use ZBateson\MailMimeParser\Message\Part\PartBuilder;
use ZBateson\MailMimeParser\Message\Part\PartStreamFilterManager;
use ZBateson\MailMimeParser\Message\PartFilter;
use ZBateson\MailMimeParser\Message\PartFilterFactory;
use ZBateson\MailMimeParser\Message\MessageHelper;
use ZBateson\MailMimeParser\Stream\StreamFactory;

/**
 * A parsed mime message with optional mime parts depending on its type.
 * 
 * A mime message may have any number of mime parts, and each part may have any
 * number of sub-parts, etc...
 *
 * @author Zaahid Bateson
 */
class Message extends MimePart
{
    /**
     * @var MessageHelper helper class with various message manipulation
     *      routines.
     */
    protected $messageHelper;

    /**
     * @param PartStreamFilterManager $partStreamFilterManager
     * @param StreamFactory $streamFactory
     * @param PartFilterFactory $partFilterFactory
     * @param HeaderFactory $headerFactory
     * @param PartBuilder $partBuilder
     * @param MessageHelper $messageHelper
     * @param StreamInterface $stream
     * @param StreamInterface $contentStream
     */
    public function __construct(
        PartStreamFilterManager $partStreamFilterManager,
        StreamFactory $streamFactory,
        PartFilterFactory $partFilterFactory,
        HeaderFactory $headerFactory,
        PartBuilder $partBuilder,
        MessageHelper $messageHelper,
        StreamInterface $stream = null,
        StreamInterface $contentStream = null
    ) {
        parent::__construct(
            $partStreamFilterManager,
            $streamFactory,
            $partFilterFactory,
            $headerFactory,
            $partBuilder,
            $stream,
            $contentStream
        );
        $this->messageHelper = $messageHelper;
    }

    /**
     * Convenience method to parse a handle or string into a Message without
     * requiring including MailMimeParser, instantiating it, and calling parse.
     * 
     * @param resource|string $handleOrString the resource handle to the input
     *        stream of the mime message, or a string containing a mime message
     */
    public static function from($handleOrString)
    {
        $mmp = new MailMimeParser();
        return $mmp->parse($handleOrString);
    }

    /**
     * Returns the text/plain part at the given index (or null if not found.)
     * 
     * @param int $index
     * @return \ZBateson\MailMimeParser\Message\Part\MimePart
     */
    public function getTextPart($index = 0)
    {
        return $this->getPart(
            $index,
            $this->partFilterFactory->newFilterFromInlineContentType('text/plain')
        );
    }
    
    /**
     * Returns the number of text/plain parts in this message.
     * 
     * @return int
     */
    public function getTextPartCount()
    {
        return $this->getPartCount(
            $this->partFilterFactory->newFilterFromInlineContentType('text/plain')
        );
    }
    
    /**
     * Returns the text/html part at the given index (or null if not found.)
     * 
     * @param $index
     * @return \ZBateson\MailMimeParser\Message\Part\MimePart
     */
    public function getHtmlPart($index = 0)
    {
        return $this->getPart(
            $index,
            $this->partFilterFactory->newFilterFromInlineContentType('text/html')
        );
    }
    
    /**
     * Returns the number of text/html parts in this message.
     * 
     * @return int
     */
    public function getHtmlPartCount()
    {
        return $this->getPartCount(
            $this->partFilterFactory->newFilterFromInlineContentType('text/html')
        );
    }

    /**
     * Returns the attachment part at the given 0-based index, or null if none
     * is set.
     * 
     * @param int $index
     * @return MessagePart
     */
    public function getAttachmentPart($index)
    {
        $attachments = $this->getAllAttachmentParts();
        if (!isset($attachments[$index])) {
            return null;
        }
        return $attachments[$index];
    }

    /**
     * Returns all attachment parts.
     * 
     * "Attachments" are any non-multipart, non-signature and any text or html
     * html part witha Content-Disposition set to  'attachment'.
     * 
     * @return MessagePart[]
     */
    public function getAllAttachmentParts()
    {
        $parts = $this->getAllParts(
            $this->partFilterFactory->newFilterFromArray([
                'multipart' => PartFilter::FILTER_EXCLUDE
            ])
        );
        return array_values(array_filter(
            $parts,
            function ($part) {
                return !(
                    $part->isTextPart()
                    && $part->getContentDisposition() === 'inline'
                );
            }
        ));
    }

    /**
     * Returns the number of attachments available.
     * 
     * @return int
     */
    public function getAttachmentCount()
    {
        return count($this->getAllAttachmentParts());
    }

    /**
     * Returns a resource handle where the 'inline' text/plain content at the
     * passed $index can be read or null if unavailable.
     * 
     * @param int $index
     * @param string $charset
     * @return resource
     */
    public function getTextStream($index = 0, $charset = MailMimeParser::DEFAULT_CHARSET)
    {
        $textPart = $this->getTextPart($index);
        if ($textPart !== null) {
            return $textPart->getContentResourceHandle($charset);
        }
        return null;
    }

    /**
     * Returns the content of the inline text/plain part at the given index.
     * 
     * Reads the entire stream content into a string and returns it.  Returns
     * null if the message doesn't have an inline text part.
     * 
     * @param int $index
     * @param string $charset
     * @return string
     */
    public function getTextContent($index = 0, $charset = MailMimeParser::DEFAULT_CHARSET)
    {
        $part = $this->getTextPart($index);
        if ($part !== null) {
            return $part->getContent($charset);
        }
        return null;
    }

    /**
     * Returns a resource handle where the 'inline' text/html content at the
     * passed $index can be read or null if unavailable.
     * 
     * @param int $index
     * @param string $charset
     * @return resource
     */
    public function getHtmlStream($index = 0, $charset = MailMimeParser::DEFAULT_CHARSET)
    {
        $htmlPart = $this->getHtmlPart($index);
        if ($htmlPart !== null) {
            return $htmlPart->getContentResourceHandle($charset);
        }
        return null;
    }

    /**
     * Returns the content of the inline text/html part at the given index.
     * 
     * Reads the entire stream content into a string and returns it.  Returns
     * null if the message doesn't have an inline html part.
     * 
     * @param int $index
     * @param string $charset
     * @return string
     */
    public function getHtmlContent($index = 0, $charset = MailMimeParser::DEFAULT_CHARSET)
    {
        $part = $this->getHtmlPart($index);
        if ($part !== null) {
            return $part->getContent($charset);
        }
        return null;
    }

    /**
     * Returns true if either a Content-Type or Mime-Version header are defined
     * in this Message.
     * 
     * @return bool
     */
    public function isMime()
    {
        $contentType = $this->getHeaderValue('Content-Type');
        $mimeVersion = $this->getHeaderValue('Mime-Version');
        return ($contentType !== null || $mimeVersion !== null);
    }

    /**
     * Sets the text/plain part of the message to the passed $stringOrHandle,
     * either creating a new part if one doesn't exist for text/plain, or
     * assigning the value of $stringOrHandle to an existing text/plain part.
     *
     * The optional $charset parameter is the charset for saving to.
     * $stringOrHandle is expected to be in UTF-8 regardless of the target
     * charset.
     *
     * @param string|resource $stringOrHandle
     * @param string $charset
     */
    public function setTextPart($stringOrHandle, $charset = 'UTF-8')
    {
        $this->messageHelper->setContentPartForMimeType(
            $this, 'text/plain', $stringOrHandle, $charset
        );
    }

    /**
     * Sets the text/html part of the message to the passed $stringOrHandle,
     * either creating a new part if one doesn't exist for text/html, or
     * assigning the value of $stringOrHandle to an existing text/html part.
     *
     * The optional $charset parameter is the charset for saving to.
     * $stringOrHandle is expected to be in UTF-8 regardless of the target
     * charset.
     *
     * @param string|resource $stringOrHandle
     * @param string $charset
     */
    public function setHtmlPart($stringOrHandle, $charset = 'UTF-8')
    {
        $this->messageHelper->setContentPartForMimeType(
            $this, 'text/html', $stringOrHandle, $charset
        );
    }

    /**
     * Removes the text/plain part of the message at the passed index if one
     * exists.  Returns true on success.
     *
     * @return bool true on success
     */
    public function removeTextPart($index = 0)
    {
        return $this->messageHelper->removePartByMimeType(
            $this, 'text/plain', $index
        );
    }

    /**
     * Removes all text/plain inline parts in this message, optionally keeping
     * other inline parts as attachments on the main message (defaults to
     * keeping them).
     *
     * @param bool $keepOtherPartsAsAttachments
     * @return bool true on success
     */
    public function removeAllTextParts($keepOtherPartsAsAttachments = true)
    {
        return $this->messageHelper->removeAllContentPartsByMimeType(
            $this, 'text/plain', $keepOtherPartsAsAttachments
        );
    }

    /**
     * Removes the html part of the message if one exists.  Returns true on
     * success.
     *
     * @return bool true on success
     */
    public function removeHtmlPart($index = 0)
    {
        return $this->messageHelper->removePartByMimeType(
            $this, 'text/html', $index
        );
    }

    /**
     * Removes all text/html inline parts in this message, optionally keeping
     * other inline parts as attachments on the main message (defaults to
     * keeping them).
     *
     * @param bool $keepOtherPartsAsAttachments
     * @return bool true on success
     */
    public function removeAllHtmlParts($keepOtherPartsAsAttachments = true)
    {
        return $this->messageHelper->removeAllContentPartsByMimeType(
            $this, 'text/html', $keepOtherPartsAsAttachments
        );
    }

    /**
     * Removes the attachment with the given index
     *
     * @param int $index
     */
    public function removeAttachmentPart($index)
    {
        $part = $this->getAttachmentPart($index);
        $this->removePart($part);
    }

    /**
     * Adds an attachment part for the passed raw data string or handle and
     * given parameters.
     *
     * @param string|handle $stringOrHandle
     * @param strubg $mimeType
     * @param string $filename
     * @param string $disposition
     */
    public function addAttachmentPart($stringOrHandle, $mimeType, $filename = null, $disposition = 'attachment')
    {
        if ($filename === null) {
            $filename = 'file' . uniqid();
        }
        $part = $this->messageHelper->createPartForAttachment($this, $mimeType, $filename, $disposition);
        $part->setContent($stringOrHandle);
        $this->addChild($part);
    }

    /**
     * Adds an attachment part using the passed file.
     *
     * Essentially creates a file stream and uses it.
     *
     * @param string $file
     * @param string $mimeType
     * @param string $filename
     * @param string $disposition
     */
    public function addAttachmentPartFromFile($file, $mimeType, $filename = null, $disposition = 'attachment')
    {
        $handle = fopen($file, 'r');
        if ($filename === null) {
            $filename = basename($file);
        }
        $this->addAttachmentPart($handle, $mimeType, $filename, $disposition);
    }

    /**
     * Returns a string containing the entire body of a signed message for
     * verification or calculating a signature.
     *
     * @return string or null if the message doesn't have any children, or the
     *      child returns null for getHandle
     */
    public function getSignedMessageAsString()
    {
        $child = $this->getChild(0);
        if ($child !== null && $child->getHandle() !== null) {
            $normalized = preg_replace(
                '/\r\n|\r|\n/',
                "\r\n",
                stream_get_contents($child->getHandle())
            );
            return $normalized;
        }
        return null;
    }

    /**
     * Returns the signature part of a multipart/signed message or null.
     *
     * The signature part is determined to always be the 2nd child of a
     * multipart/signed message, the first being the 'body'.
     *
     * Using the 'protocol' parameter of the Content-Type header is unreliable
     * in some instances (for instance a difference of x-pgp-signature versus
     * pgp-signature).
     *
     * @return MimePart
     */
    public function getSignaturePart()
    {
        $contentType = $this->getHeaderValue('Content-Type', 'text/plain');
        if (strcasecmp($contentType, 'multipart/signed') === 0) {
            return $this->getChild(1);
        } else {
            return null;
        }
    }

    /**
     * Turns the message into a multipart/signed message, moving the actual
     * message into a child part, sets the content-type of the main message to
     * multipart/signed and adds an empty signature part as well.
     *
     * After calling setAsMultipartSigned, call get
     *
     * @param string $micalg The Message Integrity Check algorithm being used
     * @param string $protocol The mime-type of the signature body
     */
    public function setAsMultipartSigned($micalg, $protocol)
    {
        $contentType = $this->getHeaderValue('Content-Type', 'text/plain');
        if (strcasecmp($contentType, 'multipart/signed') !== 0) {
            $this->messageHelper->setMessageAsMultipartSigned($this, $micalg, $protocol);
        }
        $this->messageHelper->overwrite8bitContentEncoding($this);
        $this->messageHelper->ensureHtmlPartFirstForSignedMessage($this);
        $this->setSignature('Not set');
    }

    /**
     * Sets the signature body of the message to the passed $body for a
     * multipart/signed message.
     *
     * @param string $body
     */
    public function setSignature($body)
    {
        $this->messageHelper->setSignature($this, $body);
    }
}
