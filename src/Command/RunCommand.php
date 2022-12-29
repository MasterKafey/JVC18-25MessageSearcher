<?php

namespace App\Command;

use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
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

    private int $maxPersist = 1000;

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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $browser = new HttpBrowser($this->jvc);
        for ($i = 280; true; ++$i) {
            $page = $i * 25 + 1;
            $crawler = $browser->request(Request::METHOD_GET, "https://www.jeuxvideo.com/forums/0-51-0-1-0-{$page}-0-blabla-18-25-ans.htm");
            $crawler = $crawler->filter('ul.topic-list > li[data-id]');
            $isEmpty = true;
            foreach ($crawler as $domElement) {
                $isEmpty = false;
                $uri = $domElement->childNodes->item(1)->childNodes->item(3)->attributes->getNamedItem('href')->nodeValue;
                $number = intval(trim($domElement->childNodes->item(5)->textContent));
                $this->persist($uri, $number);
            }
            if ($isEmpty) {
                break;
            }
        }

        $this->flush();

        return Command::SUCCESS;
    }

    protected function persist(string $uri, int $messageNumber): void
    {
        echo "Uri : $uri - $messageNumber\n";
        $this->entities[$uri] = ['uri' => $uri, 'messageNumber' => $messageNumber] ;
        if ($this->maxPersist <= count($this->entities)) {
            $this->flush();
        }
    }

    protected function flush(): void
    {
        $uris = array_keys($this->entities);

        $topics = $this->entityManager->getRepository(Topic::class)->findByUris($uris);
        foreach ($topics as $topic) {
            unset($this->entities[$topic->getUri()]);
        }

        foreach ($this->entities as ['uri' => $uri, 'messageNumber' => $messageNumber]) {
            $this->entityManager->persist((new Topic())->setUri($uri)->setMessageNumber(0));
        }
        echo "Flushed : " . count($this->entities) . "\n";
        $this->entityManager->flush();
        $this->entities = [];
    }
}
