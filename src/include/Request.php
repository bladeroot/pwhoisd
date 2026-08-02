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
 * Request class.
 */
class Request extends Response
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Parse and process request and response.
     */
    public function process(): void
    {
        if ($message = Application::$security->get_message()) {
            $this->response = $this->process_message($message);
        }

        if (Application::$security->get_action() == 'deny') {
            Application::$log->warning('[' . $this->client->get_address() . '] Access denied by security');

            return;
        }

        $this->define_data_section();
        $this->define_data_formats();

        Application::$log->debug('Request processed');

        if (isset($this->data_section['storage']) && is_array($this->data_section['storage'])) {
            if (!empty($this->request)) {
                $storage = new Storage($this->client, $this->data_section['storage']);

                $this->response_array = $storage->get($this->request);
            }
        }

        $this->process_response_array();
        $this->process_response();

        Application::$log->debug('Response processed');
    }

    /**
     * Search and define section data.
     */
    private function define_data_section(): void
    {
        $data = Application::$config->get('data');

        foreach ($data as $section) {
            if (!isset($section['flag']) || !isset($section['fields'])) {
                continue;
            }

            if (!empty($section['flag']) && $this->find_flag($section['flag'])) {
                $this->data_section = $section;
            }
        }

        if (is_null($this->data_section)) {
            $this->data_section = array_shift($data);
        }
    }

    /**
     * Search and define data formats.
     */
    private function define_data_formats(): void
    {
        if (!isset($this->data_section['format']) || !is_array($this->data_section['format'])) {
            return;
        }

        $data_formats = [];

        foreach ($this->data_section['format'] as $key => $section) {
            if (!isset($section[2]) || empty($section[2])) {
                $data_formats[$key] = $section;
            } elseif ($this->find_flag($section[2])) {
                $data_formats[$key] = $section;
            }
        }

        if (!empty($data_formats)) {
            $this->data_formats = $data_formats;
        }
    }

    /**
     * Find flag in request string.
     */
    private function find_flag(string $flag): bool
    {
        if (preg_match('/\s+' . preg_quote($flag) . '\s+/', ' ' . $this->request . ' ')) {
            $this->request = preg_replace('/\s+' . preg_quote($flag) . '\s+/', ' ', ' ' . $this->request . ' ', 1);
            $this->request = trim($this->request);

            return true;
        }

        return false;
    }

    /**
     * Sets current request string
     */
    public function set_request(string $request): void
    {
        $this->request = $this->complete_request = trim($request);
    }
}
