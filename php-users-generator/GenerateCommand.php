<?php

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    const DEFAULT_BATCH = 50;
    const DEFAULT_CONCURRENCY = 25;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $port;

    /**
     * @var string
     */
    private $uri;

    /**
     * @var Client
     */
    private $client;

    /**
     * @param null   $name
     * @param string $host
     * @param string $port
     * @param string $uri
     */
    public function __construct($name = null, $host, $port, $uri)
    {
        parent::__construct($name);

        $this->client = new Client();
        $this->host = $host;
        $this->port = $port;
        $this->uri = $uri;
    }


    protected function configure()
    {
        $this
            ->setName('users:generate')
            ->addOption(
                'number',
                null,
                InputOption::VALUE_OPTIONAL,
                'How many users to generate?'
            )
            ->addOption(
                'seconds',
                null,
                InputOption::VALUE_REQUIRED,
                'How long to keep generation process running'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $requests_num = self::DEFAULT_BATCH;
        if ($input->getOption('number') !== null) {
            $requests_num = (int) $input->getOption('number');
        }

        if ($input->getOption('seconds') !== null) {

            $seconds = (int) $input->getOption('seconds');

            $end = new DateTime();
            $end->modify(sprintf('+%s seconds', $seconds));

            while (time() < $end->getTimestamp()) {
                $this->makeRequests($requests_num, $output);
            }

        } else {
            $this->makeRequests($requests_num, $output);
        }
    }


    /**
     * @return Closure
     */
    private function getRequestsGenerator()
    {
        return function ($requests_num) {
            $faker = Faker\Factory::create();

            $uri = sprintf('http://%s:%s/%s', $this->host, $this->port, $this->uri);
            for ($i = 0; $i < $requests_num; $i++) {
                $person = new stdClass();
                $person->age = $faker->numberBetween(15, 55);
                $person->gender = $faker->randomElement(['male', 'female']);
                $person->firstName = $faker->firstName($person->gender);
                $person->lastName = $faker->lastName;

                $request = new Request('POST', $uri, [], json_encode($person));
                yield $request;
            }
        };
    }

    /**
     * @param string $host
     */
    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * @param string $port
     */
    public function setPort($port)
    {
        $this->port = $port;
    }

    /**
     * @param string $uri
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * @param int             $requests_num
     * @param OutputInterface $output
     */
    protected function makeRequests($requests_num, OutputInterface $output)
    {
        $requestsGenerator = $this->getRequestsGenerator();

        $pool = new Pool($this->client, $requestsGenerator($requests_num), [
            'concurrency' => self::DEFAULT_CONCURRENCY,
            'fulfilled' => function ($response, $index) use ($output) {
//                $text = $response->getBody()->getContents();
//                $output->writeln($text, OutputInterface::VERBOSITY_QUIET);
            },
            'rejected' => function ($reason, $index) use ($output) {
//                $text = sprintf('Request have been rejected: %s', $reason->getMessage());
//                $output->writeln($text, OutputInterface::VERBOSITY_QUIET);
            },
        ]);

        // Initiate the transfers and create a promise
        $promise = $pool->promise();

        // Force the pool of requests to complete.
        $promise->wait();
    }
}
