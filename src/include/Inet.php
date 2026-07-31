<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd;

/**
 * Inet class.
 */
class Inet
{
    /*
     * @const int Number of bits in the IPv4 address
     */
    const IPV4_BITS = 32;

    /*
     * @const int Number of bits in the IPv6 address
     */
    const IPV6_BITS = 128;

    /**
     * Convert an IP address to a long string
     */
    public static function ip2long(string $ip): string|int|false
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = self::ip2bin($ip);

            if (function_exists('gmp_init')) {
                return gmp_strval(gmp_init($bin, 2), 10);
            } elseif (function_exists('bcadd')) {
                $dec = '0';

                for ($i = 0; $i < strlen($bin); $i++) {
                    $dec = bcadd(bcmul($dec, '2', 0), $bin[$i], 0);
                }

                return $dec;
            }

            trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);

            return false;
        }

        return @ip2long($ip);
    }

    /**
     * Converts an IP address to binary
     */
    public static function ip2bin(string $ip): string|false
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (($ip_n = inet_pton($ip)) === false) {
                return false;
            }

            $bin = '';

            for ($bit = 15; $bit >= 0; $bit--) {
                $bin = sprintf('%08b', ord($ip_n[$bit])).$bin;
            }

            return $bin;
        }

        return base_convert((string) ip2long($ip), 10, 2);
    }

    /**
     * Returns an array containing the IP address in the subnet
     *
     * @param string|array $subnets
     * @param bool $return_all If you specify TRUE, returns the full list of entries
     */
    public static function ip_in_subnets(string $ip, string|array $subnets, bool $return_all = false): array|false
    {
        if (!is_array($subnets)) {
            $subnets = [$subnets];
        }

        $ip = self::ip2long($ip);
        $all = [];

        foreach ($subnets as $subnet) {
            $subnet = self::cidr2range($subnet);

            if (!$ip || !$subnet) {
                continue;
            }

            [$low, $high] = $subnet;

            if ($ip <= self::ip2long($high) && self::ip2long($low) <= $ip) {
                if (!$return_all) {
                    return $subnet;
                }

                $all[] = $subnet;
            } elseif ($return_all) {
                $all[] = false;
            }
        }

        if ($return_all) {
            return $all;
        }

        return false;
    }

    /**
     * Converts a CIDR block to range of addresses
     */
    public static function cidr2range(string $cidr): array|false
    {
        $cidr = explode('/', $cidr, 2);
        $ip = $cidr[0];

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $prefix_len = isset($cidr[1]) ? (int) $cidr[1] : self::IPV6_BITS;

            if ($prefix_len > self::IPV6_BITS) {
                return false;
            }

            $bits = self::IPV6_BITS - $prefix_len;

            $low_bin = inet_pton($ip);
            $low_hex = unpack('H*', $low_bin);
            $high_hex = reset($low_hex);
            $pos = 31;

            while ($bits > 0) {
                $val = hexdec(substr($high_hex, $pos, 1)) | (2 ** min(4, $bits) - 1);
                $high_hex = substr_replace($high_hex, dechex((int) $val), $pos, 1);

                $bits -= 4;
                $pos--;
            }

            $low = inet_ntop($low_bin);
            $high = inet_ntop(pack('H*', $high_hex));

            return [$low, $high];
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $prefix_len = isset($cidr[1]) ? (int) $cidr[1] : self::IPV4_BITS;

            if ($prefix_len > self::IPV4_BITS) {
                return false;
            }

            $bits = self::IPV4_BITS - $prefix_len;

            $low = long2ip((int) ip2long($ip) & (-1 << $bits));
            $high = long2ip((int) ip2long($ip) + (2 ** $bits) - 1);

            return [$low, $high];
        }

        return false;
    }
}
