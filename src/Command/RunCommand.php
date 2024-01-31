<?php

namespace App\Command;

use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:run',
    description: 'Add a short description for your command',
)]
class RunCommand extends Command
{
    private HttpClientInterface $jvc;

    private EntityManagerInterface $entityManager;

    private ParameterBagInterface $parameterBag;

    private int $maxPersist = 100;

    private array $entities = [];

    #[Required]
    public function setJVC(HttpClientInterface $jvc): void
    {
        $this->jvc = $jvc;
    }

    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    #[Required]
    public function setParameterBag(ParameterBagInterface $parameterBag): void
    {
        $this->parameterBag = $parameterBag;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $this->parameterBag->get('kernel.project_dir') . '/last_forum.yaml';
        $data = Yaml::parseFile($file);
        if ($data['date'] === null) {
            $data['date'] = date_format(date_timestamp_set(new \DateTime(), 0), 'Y-m-d');
        }
        $lastDate = new \DateTime($data['date']);
        $output->writeln('Getting last page');
        $data['page'] = $this->getLastPage($lastDate) - 1;
        $output->writeln('Get last page : ' . $data['page']);

        file_put_contents($file, Yaml::dump($data));

        $browser = new HttpBrowser($this->jvc);
        for ($page = $data['page']; $page > 0; $page -= 25) {
            $crawler = $browser->request(Request::METHOD_GET, "https://www.jeuxvideo.com/forums/0-51-0-1-0-$page-0-blabla-18-25-ans.htm");
            $crawler = $crawler->filter('ul.topic-list > li[data-id]');
            $isEmpty = true;
            foreach ($crawler as $domElement) {
                $isEmpty = false;
                $uri = $domElement->childNodes->item(1)->childNodes->item(3)->attributes->getNamedItem('href')->nodeValue;
                $number = intval(trim($domElement->childNodes->item(5)->textContent));
                $date = trim($domElement->childNodes->item(7)->textContent);
                $forumLastDate = \DateTime::createFromFormat('d/m/Y', $date);
                if (!$forumLastDate) {
                    $forumLastDate = \DateTime::createFromFormat('H:i:s', $date);
                }
                $data['date'] = $forumLastDate->format('Y-m-d');

                $this->persist($uri, $number);
            }

            $data['page'] = $page;
            file_put_contents($file, Yaml::dump($data));

            if ($isEmpty) {
                break;
            }
            $this->flush();
        }

        return Command::SUCCESS;
    }

    protected function persist(string $uri, int $messageNumber): void
    {
        echo "Uri : $uri - $messageNumber\n";
        $this->entities[$uri] = ['uri' => $uri, 'messageNumber' => $messageNumber];
        if ($this->maxPersist <= count($this->entities)) {
            $this->flush();
        }
    }

    protected function flush(): void
    {
        $uris = array_keys($this->entities);

        $topics = $this->entityManager->getRepository(Topic::class)->findByUris($uris);
        $indexedByUri = [];
        /** @var Topic $topic */
        foreach ($topics as $topic) {
            $indexedByUri[$topic->getUri()] = $topic;
            $newTopic = $this->entities[$topic->getUri()] ?? null;
            if ($topic->getMessageNumber() === $newTopic['messageNumber']) {
                unset($this->entities[$topic->getUri()]);
            }
        }

        foreach ($this->entities as ['uri' => $uri, 'messageNumber' => $messageNumber]) {
            $topic = $indexedByUri[$uri] ?? (new Topic())->setUri($uri);
            $this->entityManager->persist($topic->setMessageNumber($messageNumber));
        }

        echo "Flushed : " . count($this->entities) . "\n";
        $this->entityManager->flush();
        $this->entities = [];
    }

    private function isGreater(int $number, \DateTime $lastDate): int
    {
        $browser = new HttpBrowser($this->jvc);
        $crawler = $browser->request(Request::METHOD_GET, "https://www.jeuxvideo.com/forums/0-51-0-1-0-{$number}-0-blabla-18-25-ans.htm");
        if ($browser->getResponse()->getStatusCode() === 404) {
            return false;
        }

        $crawler = $crawler->filter('ul.topic-list > li[data-id]');
        foreach ($crawler as $domElement) {
            $lastMessageDate = trim($domElement->childNodes->item(7)->textContent);
            $forumLastDate = \DateTime::createFromFormat('d/m/Y', $lastMessageDate);
            if (!$forumLastDate) {
                $forumLastDate = \DateTime::createFromFormat('H:i:s', $lastMessageDate);
            }
            if ($forumLastDate->getTimestamp() < $lastDate->getTimestamp()) {
                return false;
            }
        }

        return true;
    }

    private function getLastPage(\DateTime $lastDate)
    {
        $low = 0;
        $high = 1;

        while ($this->isGreater($high, $lastDate)) {
            $low = $high;
            $high *= 2;
        }

        while ($low <= $high) {
            $mid = $low + (int)(($high - $low) / 2);
            if ($this->isGreater($mid, $lastDate)) {
                $low = $mid + 1;
            } else {
                if ($mid == 0 || $this->isGreater($mid - 1, $lastDate)) {
                    return $mid;
                } else {
                    $high = $mid - 1;
                }
            }
        }

        return -1;
    }
}
