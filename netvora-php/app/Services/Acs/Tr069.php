<?php

declare(strict_types=1);

namespace App\Services\Acs;

/**
 * TR-069 / CWMP SOAP builder & parser (cwmp-1-0).
 * Minimal but real: Inform, InformResponse, Reboot, FactoryReset,
 * Set/GetParameterValues, Download (firmware).
 */
final class Tr069
{
    private const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const NS_CWMP = 'urn:dslforum-org:cwmp-1-0';

    /** Detect the RPC method name from a CWMP request body. */
    public static function detectMethod(string $xml): ?string
    {
        if (trim($xml) === '') {
            return 'EmptyPost';
        }
        $body = self::stripBody($xml);
        // First element inside the SOAP Body, ignoring optional ns prefix + attributes
        if (preg_match('/<\s*(?:[\w-]+:)?([A-Za-z]\w+)[\s>\/]/', $body, $m)) {
            return $m[1];
        }
        return null;
    }

    /** Parse an Inform request into a normalised array. */
    public static function parseInform(string $xml): array
    {
        $sx = self::load($xml);
        $out = [
            'manufacturer' => null, 'oui' => null, 'product_class' => null, 'serial' => null,
            'events' => [], 'parameters' => [],
        ];
        if ($sx === null) {
            return $out;
        }
        $inform = $sx->children(self::NS_SOAP)->Body->children(self::NS_CWMP)->Inform;
        if ($inform->getName() !== 'Inform') {
            return $out;
        }
        // DeviceId / Event / ParameterList are in the empty namespace.
        // Call children('') fresh each time (storing a children() collection loses context).
        $out['manufacturer']  = (string) $inform->children('')->DeviceId->Manufacturer;
        $out['oui']           = (string) $inform->children('')->DeviceId->OUI;
        $out['product_class'] = (string) $inform->children('')->DeviceId->ProductClass;
        $out['serial']        = (string) $inform->children('')->DeviceId->SerialNumber;

        foreach ($inform->children('')->Event->EventStruct as $ev) {
            $out['events'][] = (string) $ev->EventCode;
        }
        foreach ($inform->children('')->ParameterList->ParameterValueStruct as $p) {
            $out['parameters'][(string) $p->Name] = (string) $p->Value;
        }
        return $out;
    }

    /** Parse a GetParameterValuesResponse into name=>value. */
    public static function parseParameterValues(string $xml): array
    {
        $sx = self::load($xml);
        $out = [];
        if ($sx === null) {
            return $out;
        }
        $resp = $sx->children(self::NS_SOAP)->Body->children(self::NS_CWMP)->GetParameterValuesResponse;
        if ($resp->getName() !== 'GetParameterValuesResponse') {
            return $out;
        }
        foreach ($resp->children('')->ParameterList->ParameterValueStruct as $p) {
            $out[(string) $p->Name] = (string) $p->Value;
        }
        return $out;
    }

    public static function informResponse(): string
    {
        return self::envelope(
            '<cwmp:InformResponse><MaxEnvelopes>1</MaxEnvelopes></cwmp:InformResponse>',
            'inform-resp'
        );
    }

    public static function reboot(string $commandKey = 'netvora-reboot'): string
    {
        return self::envelope(
            "<cwmp:Reboot><CommandKey>{$commandKey}</CommandKey></cwmp:Reboot>",
            'reboot'
        );
    }

    public static function factoryReset(): string
    {
        return self::envelope('<cwmp:FactoryReset></cwmp:FactoryReset>', 'reset');
    }

    /** @param array<int,array{0:string,1:string,2:string}> $params [name, value, xsdType] */
    public static function setParameterValues(array $params, string $key = 'netvora-set'): string
    {
        $items = '';
        foreach ($params as [$name, $value, $type]) {
            $type = $type ?: 'xsd:string';
            $items .= "<ParameterValueStruct><Name>" . self::esc($name) . "</Name>"
                . "<Value xsi:type=\"{$type}\">" . self::esc($value) . "</Value></ParameterValueStruct>";
        }
        $count = count($params);
        $body = "<cwmp:SetParameterValues>"
            . "<ParameterList soap-enc:arrayType=\"cwmp:ParameterValueStruct[{$count}]\">{$items}</ParameterList>"
            . "<ParameterKey>{$key}</ParameterKey></cwmp:SetParameterValues>";
        return self::envelope($body, 'set');
    }

    /** @param string[] $names */
    public static function getParameterValues(array $names): string
    {
        $items = '';
        foreach ($names as $n) {
            $items .= '<string>' . self::esc($n) . '</string>';
        }
        $count = count($names);
        $body = "<cwmp:GetParameterValues>"
            . "<ParameterNames soap-enc:arrayType=\"xsd:string[{$count}]\">{$items}</ParameterNames>"
            . "</cwmp:GetParameterValues>";
        return self::envelope($body, 'get');
    }

    public static function download(string $url, string $fileType = '1 Firmware Upgrade Image', string $key = 'netvora-fw'): string
    {
        $body = "<cwmp:Download>"
            . "<CommandKey>{$key}</CommandKey>"
            . "<FileType>" . self::esc($fileType) . "</FileType>"
            . "<URL>" . self::esc($url) . "</URL>"
            . "<FileSize>0</FileSize><DelaySeconds>0</DelaySeconds>"
            . "</cwmp:Download>";
        return self::envelope($body, 'download');
    }

    // ---------- internals ----------
    private static function envelope(string $body, string $id): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap-env:Envelope '
            . 'xmlns:soap-env="' . self::NS_SOAP . '" '
            . 'xmlns:soap-enc="http://schemas.xmlsoap.org/soap/encoding/" '
            . 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xmlns:cwmp="' . self::NS_CWMP . '">'
            . '<soap-env:Header><cwmp:ID soap-env:mustUnderstand="1">' . $id . '</cwmp:ID></soap-env:Header>'
            . '<soap-env:Body>' . $body . '</soap-env:Body>'
            . '</soap-env:Envelope>';
    }

    private static function load(string $xml): ?\SimpleXMLElement
    {
        if (trim($xml) === '') {
            return null;
        }
        $prev = libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);
        return $sx === false ? null : $sx;
    }

    private static function stripBody(string $xml): string
    {
        if (preg_match('/<(?:[\w-]+:)?Body[^>]*>(.*)<\/(?:[\w-]+:)?Body>/s', $xml, $m)) {
            return $m[1];
        }
        return $xml;
    }

    private static function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
