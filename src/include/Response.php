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
 * Abstract Response class.
 */
abstract class Response
{
    protected string $request = '';

    protected string $complete_request = '';

    protected ?array $data_section = null;

    protected ?array $data_formats = null;

    protected string $response = '';

    /*
     * @var array|bool Response array. May be `false` when Storage::get()
     * (e.g. a failed/never-connected provider) reported no data at all.
     */
    protected array|bool $response_array = [];

    /**
     * Process response.
     */
    protected function process_response(): void
    {
        if (!is_array($this->data_section['fields']) || empty($this->data_section['fields'])) {
            return;
        }

        $hide_empty = true;

        if (isset($this->data_section['hide_empty'])) {
            $hide_empty = $this->data_section['hide_empty'];
        }

        $response_array = $response_array_names_len = [];

        foreach ($this->data_section['fields'] as $field) {
            if (!is_array($field)) {
                continue;
            }

            if (empty($field)) {
                $response_array[] = '';

                continue;
            }

            $field[0] = $this->process_response_macro($field[0]);

            if ($field[0] === false) {
                continue;
            }

            if (sizeof($field) > 1) {
                $field_flag = $field[2] ?? null;

                if (isset($this->response_array[$field[1]]) && $this->response_array[$field[1]] !== null) {
                    if (!empty($this->response_array[$field[1]]) || $hide_empty === false) {
                        if ($field_flag === null) {
                            $values = explode("\n", str_replace("\r\n", "\n", $this->response_array[$field[1]]));

                            if (!preg_match('/\:$/', trim($field[0]))) {
                                $field[0] .= ':';
                            }

                            foreach ($values as $value) {
                                $response_array[] = [$field[0], $value];
                            }

                            $response_array_names_len[] = strlen($field[0]);
                        } elseif ($field_flag === true) {
                            $response_array[] = $field[0];
                        }
                    }
                } elseif ($field_flag === false) {
                    $response_array[] = $field[0];
                }
            } else {
                $response_array[] = $field[0];
            }
        }

        $max_len = 0;

        if (!empty($response_array_names_len)) {
            $max_len = max($response_array_names_len);
        }

        $response = [];

        foreach ($response_array as $field) {
            if (is_array($field)) {
                if (isset($this->data_section['spacing']) && $this->data_section['spacing']) {
                    $response[] = $field[0] . ' ' . str_repeat(' ', $max_len - strlen($field[0])) . $field[1];
                } else {
                    $response[] = $field[0] . $field[1];
                }
            } else {
                $response[] = $field;
            }
        }

        // Show invalid_request message
        if ((empty($this->complete_request) && empty($this->response_array)) || empty($response)) {
            $invalid_request = ['Invalid request.'];

            if (isset($this->data_section['invalid_request'])) {
                $invalid_request = $this->data_section['invalid_request'];
            }

            $response = [$this->process_message($invalid_request)];
        }

        $this->response = implode("\n", $response);
    }

    /**
     * Process response array.
     */
    protected function process_response_array(): void
    {
        if (!is_array($this->response_array) || empty($this->response_array)) {
            return;
        }

        if (is_array($this->data_formats) && !empty($this->data_formats)) {
            foreach ($this->data_formats as $data_format) {
                if (sizeof($data_format) < 2) {
                    continue;
                }

                @[$field, $format] = $data_format;

                if (($eval = $this->process_response_macro($format, true)) === false) {
                    continue;
                }

                if (!empty($eval)) {
                    eval("\$format = $eval;");
                }

                if (isset($this->response_array[$field]) && $this->response_array[$field] == '0000-00-00 00:00:00') {
                    $this->response_array[$field] = '';
                } else {
                    $this->response_array[$field] = trim($format);
                }
            }
        }
    }

    /**
     * Process message.
     *
     * @param string|array $message Message string (or lines) to process
     */
    protected function process_message(string|array $message, bool $quote = false, bool $recursion = true): string
    {
        if (!is_array($message)) {
            $message = [$message];
        }

        $lines = [];

        foreach ($message as $line) {
            $line = $this->process_response_macro($line, $quote, $recursion);

            if ($line !== false) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Process macro in response string.
     */
    protected function process_response_macro(string $string, bool $quote = false, bool $recursion = true): string|false
    {
        // System macro
        $macros = [
            '_request_'     => $this->request,
            '_client_ip_'   => $this->client->get_address(),
            '_client_port_' => $this->client->get_port(),
        ];

        // Messages based macro
        foreach (Application::$config->get('messages') as $message_key => $message_value) {
            $macros['%' . $message_key . '%'] = $message_value;
        }

        // Storage response macro
        if (is_array($this->response_array) && !empty($this->response_array)) {
            $macros += $this->response_array;
        }

        foreach ($macros as $macro => $value) {
            if ($value !== null && strpos($string, '{' . $macro . '}') !== false) {
                // Recursion
                if (is_array($value)) {
                    if ($recursion === false) {
                        continue;
                    }

                    $value = $this->process_message($value, $quote, false);
                }

                if ($quote) {
                    $value = '"' . $value . '"';
                }

                $string = str_replace('{' . $macro . '}', $value, $string);
            }
        }

        return !preg_match('/\{[\%\w]+\}/', $string) ? $string : false;
    }

    /**
     * Gets response string.
     */
    public function get_response(): string
    {
        return $this->response;
    }
}
