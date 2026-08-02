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
 * Security class.
 */
class Security
{
    /*
     * @const int One second period
     */
    const interval_second = 1;

    /*
     * @const int One minute period
     */
    const interval_minute = 60;

    /*
     * @const int One hour period
     */
    const interval_hour = 3600;

    /*
     * @const int One day period
     */
    const interval_day = 86400;

    /*
     * @var array Interval pools [[interval, time(), key, counter], ...]
     */
    private array $interval_pool = [];

    /*
     * @var array Intervals presented during the current request, keyed by interval key
     */
    private array $intervals = [];

    private ?Client $client = null;

    private array $rules;

    private string|array|false|null $message = null;

    private ?string $action = null;

    /**
     * Assigning class properties and parse rules configuration.
     */
    public function __construct()
    {
        $this->rules = Application::$config->get('security.rules');

        $this->parse_rules();
    }

    /**
     * Initialize security by client request.
     */
    public function initialize(Client $client): void
    {
        $this->client = $client;

        $this->process_rules();
    }

    /**
     * Process defined access control rules.
     */
    private function process_rules(): void
    {
        $this->message = null;
        $this->action = null;
        $this->intervals = [];

        foreach (array_reverse($this->rules) as $rule) {
            if ($this->process_conditions($rule['conditions'])) {
                $this->message = Application::$config->get('messages.' . $rule['message'], false);
                $this->action = $rule['action'];
            }
        }

        // Update presented intervals
        foreach ($this->intervals as $key => $interval) {
            $this->interval_check($interval, $key, true);
        }
    }

    /**
     * Process rule condition.
     */
    private function process_conditions(array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $client_address = $this->client->get_address();

        $expressions = [true];

        foreach (['client_ip', 'requests', 'rate'] as $matched_condition) {
            foreach ($conditions as $condition) {
                $expression = null;

                if ($condition['l'] != $matched_condition) {
                    continue;
                }

                switch ($condition['l']) {
                    // Match client IP policy
                    case 'client_ip':
                        $expression = $this->compare_client_ip($condition['r']);
                        break;

                    // Client-based interval policy
                    case 'requests':
                        $expression = $this->compare_interval(
                            $condition['op'],
                            $condition['r'],
                            $client_address
                        );
                        break;

                    // Global interval policy
                    case 'rate':
                        $expression = $this->compare_interval(
                            $condition['op'],
                            $condition['r']
                        );
                        break;
                }

                if ($expression !== null) {
                    $expressions[] = $expression;

                    if ($expression === false) {
                        break 2; // stop conditions checking
                    }
                }
            }
        }

        return (bool) min($expressions);
    }

    /**
     * Compares interval method.
     */
    private function compare_interval(string $op, string $variable, ?string $key = null): bool
    {
        @[$variable, $interval] = explode('/', $variable, 2);

        switch ($interval) {
            case 'sec': $seconds = self::interval_second; break;
            case 'min': $seconds = self::interval_minute; break;
            case 'hr':  $seconds = self::interval_hour;   break;
            case 'day': $seconds = self::interval_day;    break;
        }

        if (!isset($seconds) || empty($variable)) {
            return false;
        }

        $counter = $this->interval_check($seconds, $key);

        // $key is NULL for the global 'rate' policy (no per-client key) - array
        // keys can't be NULL as of PHP 8.1, so normalize it the same way an
        // unset array key would read back (empty string).
        $this->intervals[$key ?? ''] = $seconds;

        $variable = (float) $variable;

        switch ($op) {
            case '==': return $counter == $variable;
            case '!=': return $counter != $variable;
            case '>=': return $counter >= $variable;
            case '<=': return $counter <= $variable;
            case '>': return $counter > $variable;
            case '<': return $counter < $variable;
        }

        return false;
    }

    /**
     * Compares IP address method.
     *
     * @param string|array $variable
     */
    private function compare_client_ip(string|array $variable): array|false
    {
        return Inet::ip_in_subnets($this->client->get_address(), $variable);
    }

    /**
     * Checks all intervals and returns current counter value.
     */
    private function interval_check(int $interval = self::interval_second, ?string $key = null, bool $update = false): int
    {
        $counter = false;

        foreach ($this->interval_pool as $id => $element) {
            if ($element[1] <= time() - $element[0]) {
                unset($this->interval_pool[$id]);

                continue;
            }

            if ($element[0] == $interval && $element[2] == $key) {
                if ($update) {
                    $counter = ++$this->interval_pool[$id][3];
                } else {
                    $counter = $element[3];
                }
            }
        }

        if ($counter === false) {
            $counter = 1;

            if ($update) {
                $this->interval_pool[] = [$interval, time(), $key, ++$counter];
            }
        }

        return $counter;
    }

    /**
     * Parse access control rules configuration.
     */
    private function parse_rules(): void
    {
        $rules = [];

        foreach ($this->rules as $id => $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $rules[$id]['action'] = null;
            $rules[$id]['message'] = null;
            $rules[$id]['conditions'] = [];

            foreach ($rule as $rule_part) {
                // Assigning action
                if (is_null($rules[$id]['action']) && ($rule_part == 'allow' || $rule_part == 'deny' || $rule_part == 'drop')) {
                    $rules[$id]['action'] = $rule_part;
                } elseif (is_null($rules[$id]['message']) && is_string($rule_part)) {
                    // Assigning message
                    $rules[$id]['message'] = $rule_part;
                } elseif (is_array($rule_part) && sizeof($rule_part) >= 2) {
                    // Assigning conditions
                    $rules[$id]['conditions'][] = $this->parse_condition($rule_part);
                }
            }

            if (is_null($rules[$id]['action'])) {
                unset($rules[$id]);
            }
        }

        $this->rules = $rules;
    }

    /**
     * Parse conditions in rule configuration.
     */
    private function parse_condition(array $condition_part): array
    {
        $condition = [];

        if (sizeof($condition_part) >= 3) {
            $condition['l'] = $condition_part[0];
            $condition['op'] = $condition_part[1];
            $condition['r'] = $condition_part[2];
        } else {
            $condition['l'] = $condition_part[0];
            $condition['r'] = $condition_part[1];
        }

        if (!isset($condition['op']) || !in_array($condition['op'], ['==', '!=', '=<', '=>', '<', '>'])) {
            $condition['op'] = '==';
        }

        return $condition;
    }

    /**
     * Gets message sent to client.
     */
    public function get_message(): string|array|false|null
    {
        return $this->message;
    }

    /**
     * Gets last assigned action.
     */
    public function get_action(): string
    {
        return strtolower($this->action ?? '');
    }
}
