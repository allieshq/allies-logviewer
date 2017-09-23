<?php

namespace Allies\Bundle\LogViewerBundle\Provider;

class FileProvider
{
    
/******************************************************************************
 * PROPERTIES
 ******************************************************************************/
    
    /**
     * @var AppKernel
     */
    protected $kernel;
    
/******************************************************************************
 * MAGIC
 ******************************************************************************/
    
    /**
     * @param \AppKernel $kernel
     */
    public function __construct(\AppKernel $kernel)
    {
        $this->kernel = $kernel;
    }
    
/******************************************************************************
 * METHODS
 ******************************************************************************/
    
    /**
     * @return array
     */
    public function getLogFilesSummary()
    {
        $return = [];
        
        $di = new \DirectoryIterator($this->kernel->getLogDir());
        foreach ($di as $fileInfo) {
            if (!$fileInfo->isDot() && !$fileInfo->isDir() && 
                in_array($fileInfo->getExtension(), ['log', 'csv'])) {
                $return[] = [
                    'filename' => $fileInfo->getFilename(),
                    'mtime' => $fileInfo->getMTime(),
                    'mtime_readable' => date('Y-m-d H:i:s', $fileInfo->getMTime()),
                    'size' => $fileInfo->getSize(),
                    'size_readable' => $this->convertFileSize($fileInfo->getSize()),
                    'readable' => $fileInfo->isReadable(),
                    'lines' => $this->getFileLineCount($fileInfo->getFilename()),
                    'extension' => $fileInfo->getExtension(),
                ];
            }
        }
        
        return $return;
    }
    
    /**
     * @link https://stackoverflow.com/a/2162528
     * 
     * @param string $filename
     * @throws \RuntimeException
     */
    public function getFileLineCount($filename)
    {
        $file = $this->getLogFilepath($filename);
        if (!$file) {
            throw new \RuntimeException(sprintf(
                    "Cannot find file %s",
                    $filename
                ));
        }
        
        $lineCount = 0;
        $handle = fopen($file, "r");
        while(!feof($handle)){
            $line = fgets($handle);
            $lineCount++;
        }

        fclose($handle);
        
        return $lineCount;
    }
    
    /**
     * 
     * @param string $filename
     * @param integer $start
     * @param integer $lines
     * @return array
     */
    public function readFileParsed($filename, $start=-30, $lines=30)
    {
        if (substr($filename, -4) == '.csv') {
            return $this->parseReadArrayCsv(
                $this->readFile($filename, 0, 1),
                $this->readFile($filename, $start, $lines)
            );
        } else {
            return $this->parseReadArray($this->readFile($filename, $start, $lines));
        }
    }
    
    /**
     * @param string $filename
     * @param string $pattern
     * @param integer $start
     * @param integer $lines
     * @param boolean $caseSensitive
     * @return array
     */
    public function grepFileParsed($filename, $pattern, $start=0, $lines=30, $caseSensitive=false)
    {
        return $this->parseReadArray($this->grepFile($filename, $pattern, $start, $lines, $caseSensitive));
    }
    
    /**
     * @param array $readHeaders
     * @param array $readArray
     * @return array
     * @throws \InvalidArgumentException
     */
    public function parseReadArrayCsv(array $readHeaders, array $readArray)
    {
        if (empty($readHeaders)) {
            return ['headers' => [], 'lines' => []];
        }
        if (count($readHeaders) > 1 || !isset($readHeaders[0])) {
            throw new \InvalidArgumentException("Headers must have a single element which is zero indexes");
        }
        if (isset($readArray[0])) {
            unset($readArray[0]);
        }
        
        return [
            'headers' => current(array_map('str_getcsv', array_map('trim', $readHeaders))),
            'lines' => array_map('str_getcsv', array_map('trim', $readArray)),
        ];
    }
    
    /**
     * @param array $readArray
     * @return array
     */
    public function parseReadArray(array $readArray)
    {
        $return = [];
        foreach ($readArray as $lineNo => $line) {
            $matches = [];
            preg_match_all("#^\s*\[?(\d{4}-\d{2}-\d{2}(T| )\d{2}:\d{2}:\d{2}(\+\d{2}:\d{2})?)\]?\s+([^:]+):\s*(.*)$#", $line, $matches);
            if (isset($matches[1][0]) && isset($matches[4][0]) && isset($matches[5][0])) {
                $return[] = [
                    'lineNo' => $lineNo+1,
                    'datetime' => $matches[1][0],
                    'type' => $matches[4][0],
                    'message' => $matches[5][0],
                ];
            } else {
                $return[] = [
                    'lineNo' => $lineNo+1,
                    'datetime' => null,
                    'type' => null,
                    'message' => $line,
                ];
            }
        }
        
        return $return;
    }
    
    /**
     * @param string $filename
     * @param integer $start
     * @param integer $lines
     * @return array
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function readFile($filename, $start=-30, $lines=30)
    {
        $start = (int)$start;
        $lines = (int)$lines;
        
        if ($lines <= 0) {
            throw new \InvalidArgumentException(sprintf(
                    "Total lines to read must be greater than zero. '%s' passed",
                    $lines
                ));
        }
        
        $file = $this->getLogFilepath($filename);
        if (!$file) {
            throw new \RuntimeException(sprintf(
                    "Cannot find file %s",
                    $filename
                ));
        }
        
        $fileLineCount = $this->getFileLineCount($file);
        
        if ($start < 0) {
            $start = max(0, $fileLineCount+$start);
        }
        
        $handle = fopen($file, "r");
        $curLineNo = 0;
        $lineCount = 0;
        $end = false;
        $return = [];
        
        while (!feof($handle) && $lineCount < $lines){
            $line = fgets($handle);
            if ($curLineNo >= $start) {
                $return[$curLineNo] = $line;
                $lineCount++;
            }
            $curLineNo++;
        }
        
        fclose($handle);
        
        return $return;
    }
    
    /**
     * @param string $filename
     * @param string $pattern
     * @param integer $start
     * @param integer $lines
     * @param boolean $caseSensitive
     * @return array
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \BadMethodCallException
     */
    public function grepFile($filename, $pattern, $start=0, $lines=30, $caseSensitive=false)
    {
        $start = (int)$start;
        $lines = (int)$lines;
        
        if ($lines <= 0) {
            throw new \InvalidArgumentException(sprintf(
                    "Total lines to read must be greater than zero. '%s' passed",
                    $lines
                ));
        }
        
        $file = $this->getLogFilePath($filename);
        if (!$file) {
            throw new \RuntimeException(sprintf(
                    "Cannot find file %s",
                    $filename
                ));
        }
        
        if ($start < 0) {
            throw new \BadMethodCallException(sprintf(
                    "Negative start values not currently support for grep. '%s' passed",
                    $lines
                ));
        }
        
        $delimiters = ['/','#','@','~','|'];
        $regex = null;
        foreach ($delimiters as $delimiter) {
            if (false !== strpos($pattern, $delimiter)) {
                continue;
            }
            
            $regex = sprintf("%s%s%s%s",
                    $delimiter,
                    $pattern,
                    $delimiter,
                    ($caseSensitive) ? null : 'i'
                );
            break;
        }
        
        if (is_null($regex)) {
            throw new \RuntimeException(sprintf(
                    "Could not find valid delimiter. Pattern: %s ; Delimiters: %s",
                    $pattern,
                    implode(', ', $delimiters)
                ));
        }
        
        $handle = fopen($file, "r");
        $curLineNo = 0;
        $matchingLineCount = 0;
        $returnLineCount = 0;
        $end = false;
        $return = [];
        
        while (!feof($handle) && $returnLineCount < $lines) {
            $line = fgets($handle);
            $matches = [];
            if (preg_match($regex, $line, $matches)) {
                $matchingLineCount++;
                if ($matchingLineCount >= $start) {
                    $return[$curLineNo] = $line;
                    $returnLineCount++;
                }
            }
            
            $curLineNo++;
        }
        fclose($handle);
        
        return $return;
    }
    
/******************************************************************************
 * INTERNAL
 ******************************************************************************/
    
    /**
     * @param string $filename
     * @return string|false
     */
    protected function getLogFilepath($filename)
    {
        $logDir = $this->kernel->getLogDir();
        $filename = str_replace($logDir, null, $filename);
        $filename = ltrim($filename, './\\');
        
        return realpath($logDir.DIRECTORY_SEPARATOR.$filename);
    }
    
    /**
     * @param integer $fileSize
     * @return string
     */
    protected function convertFileSize($fileSize)
    {
        $fileSize = (int)$fileSize;
        switch (true) {
            case $fileSize >= (1024 * 1024 * 1024) :
                $humanFs = $fileSize / (1024*1024*1024);
                $unit = 'GB';
                break;
            case $fileSize >= (1024 * 1024) :
                $humanFs = $fileSize / (1024*1024);
                $unit = 'MB';
                break;
            case $fileSize >= (1024) :
                $humanFs = $fileSize / (1024);
                $unit = 'KB';
                break;
            default :
                $humanFs = $fileSize;
                $unit = '';
                break;
        }
        
        return sprintf("%s %s", number_format($humanFs, 2), $unit);
    }
    
}