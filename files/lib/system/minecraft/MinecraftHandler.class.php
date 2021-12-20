<?php

namespace wcf\system\minecraft;

use wcf\system\exception\MinecraftException;

/**
 * MinecraftHandler class
 *
 * @author   xXSchrandXx
 * @license  Creative Commons Zero v1.0 Universal (http://creativecommons.org/publicdomain/zero/1.0/)
 * @package  WoltLabSuite\Core\System\Minecraft
 */
class MinecraftHandler implements IMinecraftHandler
{

    /**
     * @see https://gist.github.com/tehbeard/1292348 Based on the work of tehbeard.
     */
    protected $fsock;

    /**
     * @see https://gist.github.com/tehbeard/1292348 Based on the work of tehbeard.
     */
    protected $packID;

    /**
     * the hostname/ip of your Minecraft server
     * @var string
     */
    protected $hostname;

    /**
     * the server rcon port of your Minecraft server (standard = 25575)
     * @var int
     */
    protected $port;

    /**
     * Password of server rcon
     * @var string
     */
    protected $password;

    /**
     * @inheritDoc
     */
    public function __construct($hostname, $port, $password)
    {
        $this->hostname = $hostname;
        $this->port = $port;
        $this->password = $password;

        $this->connect();
    }

    /**
     * @inheritDoc
     */
    public function __destruct()
    {
        @fclose($this->fsock);
    }

    /**
     * @inheritDoc
     * @see https://gist.github.com/tehbeard/1292348 Based on the work of tehbeard.
     */
    public function connect()
    {
        $this->fsock = @fsockopen($this->hostname, $this->port, $errno, $errstr, 30);

        if (!$this->fsock) {
            throw new MinecraftException("Can't connect.");
        }

        $this->setTimeout($this->fsock, 2, 500);

        $this->login();
    }

    /**
     * @inheritDoc
     * @see https://gist.github.com/tehbeard/1292348 Based on the work of tehbeard.
     */
    public function login()
    {
        $packID = $this->write(3, $this->password);

        // Real response (id: -1 = failure)
        $ret = $this->packetRead($packID);
        if ($ret[0]['ID'] == 1) {
            return;
        }
        throw new MinecraftException("Wrong password.");
    }

    /**
     * @see https://gist.github.com/tehbeard/1292348 Based on the work of tehbeard.
     */
    public function setTimeout(&$res, $s, $m = 0)
    {
        if (\version_compare(\phpversion(), '4.3.0', '<')) {
            return \socket_set_timeout($res, $s, $m);
        }
        return \stream_set_timeout($res, $s, $m);
    }

    /**
     * Writes the packat.
     * @see https://gist.github.com/tehbeard/1292348 Based on the work of tehbeard.
     * @param   int    $cmd
     * @param   string $command
     * @return  int    packet identificator
     */
    private function write(int $cmd, string $command)
    {
        // Get and increment the packet id
        $packID = ++$this->packID;

        // Put our packet together
        $data = \pack("VV", $packID, $cmd) . $command . \chr(0);

        // Prefix the packet size
        $data = \pack("V", \strlen($data)) . $data;

        // Send packet
        \fwrite($this->fsock, $data, \strlen($data));

        // In case we want it later we'll return the packet id
        return $packID;
    }

    /**
     * @see https://gist.github.com/tehbeard/1292348 Based on the work of tehbeard.
     */
    private function packetRead($packID)
    {
        //Declare the return array
        $retarray = array();
        //Fetch the packet size
        while ($size = @\fread($this->fsock, 4)) {
            $size = \unpack('V1Size', $size);
            //Work around valve breaking the protocol
            if ($size["Size"] > 4096) {
                //pad with 8 nulls
                $packet = "\x00\x00\x00\x00\x00\x00\x00\x00" . \fread($this->fsock, 4096);
            } else {
                //Read the packet back
                $packet = \fread($this->fsock, $size["Size"]);
            }
            \array_push($retarray, \unpack("V1ID/V1Response/a*CMD", $packet));
        }
        return $retarray;
    }

    /**
     * @inheritDoc
     * @see https://gist.github.com/tehbeard/1292348 Based on the work of tehbeard.
     */
    public function parseResult($packID)
    {
        $Packets = $this->packetRead($packID);

        foreach ($Packets as $pack) {
            if (isset($ret[$pack['ID']])) {
                $ret[$pack['ID']]['CMD'] += \rtrim($pack['CMD']);
            } else {
                $ret[$pack['ID']] = [
                    'Response' => $pack['Response'],
                    'CMD' => $pack['CMD']
                ];
            }
        }
        return $ret;
    }

    /**
     * @inheritDoc
     * @see https://gist.github.com/tehbeard/1292348 Based on the work of tehbeard.
     */
    public function execute(string $command)
    {
        return $this->write(2, $command);
    }

    /**
     * @inheritDoc
     * @see https://gist.github.com/tehbeard/1292348 Based on the work of tehbeard.
     */
    public function call(string $command)
    {
        $packID = $this->execute($command);
        
        $ret = $this->parseResult($packID);

        return $ret[$packID];
    }
}
