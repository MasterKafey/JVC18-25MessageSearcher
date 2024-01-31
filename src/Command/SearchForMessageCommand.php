<?php

namespace App\Command;

use App\Entity\Message;
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

#[AsCommand(name: 'app:message:search')]
class SearchForMessageCommand extends Command
{
    private HttpClientInterface $client;

    private string $pseudo;

    private EntityManagerInterface $entityManager;

    #[Required]
    public function setHttpClient(HttpClientInterface $client): void
    {
        $this->client = $client;
    }

    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    public function setPseudo(string $pseudoToSearch): void
    {
        $this->pseudo = $pseudoToSearch;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {

        while(true) {
            $topic = $this->entityManager->getRepository(Topic::class)->findUnreadTopic();
            if (null === $topic) {
                break;
            }
            dump('id: ' . $topic->getId() . " uri : " . $topic->getUri());
            $this->searchMessageFromCreator($topic, $this->pseudo, $totalMessage);

            $topic
                ->setMessageRead($totalMessage)
                ->setMessageNumber($totalMessage)
            ;
            $this->entityManager->persist($topic);
            $this->entityManager->flush();
        }
        return Command::SUCCESS;
    }

    public function searchMessageFromCreator(Topic $topic, string $creator, &$totalMessage): void
    {
        $creator = strtolower($creator);
        $browser = new HttpBrowser($this->client);
        $link = 'https://www.jeuxvideo.com' . $topic->getUri();
        $totalMessage = $topic->getMessageRead();
        $i = intval($totalMessage / 20);
        do {
            try {
                $crawler = $browser->request(Request::METHOD_GET, $url = $this->nextPage($link, ++$i));
                if ($crawler->getBaseHref() !== $url) {
                    break;
                }
                $crawler = $crawler->filter('.conteneur-message');
                foreach ($crawler as $childNode) {
                    $author = trim($childNode->childNodes->item(1)->childNodes->item(3)->textContent);
                    if ($childNode->childNodes->item(1)->childNodes->item(11) === null) {
                        continue;
                    }
                    ++$totalMessage;
                    $topic->setMessageRead($totalMessage);
                    $datetime = $this->convertDateTime(trim($childNode->childNodes->item(1)->childNodes->item(11)->textContent));
                    $content = trim($childNode->childNodes->item(3)->textContent);

                    if (strtolower($creator) === strtolower($author)) {
                        $this->entityManager->persist((new Message())
                            ->setAuthor($author)
                            ->setTopic($topic)
                            ->setContent($content)
                            ->setCreationDateTime($datetime));
                    }
                }
                $this->entityManager->persist($topic);
                $this->entityManager->flush();
            } catch (\Exception) {}
        } while (true);
    }

    public function convertDateTime(string $dateTime): bool|\DateTime
    {
        $dateFormat = "d F Y à H:i:s";

        $monthNames = array(
            "janvier" => "January",
            "février" => "February",
            "mars" => "March",
            "avril" => "April",
            "mai" => "May",
            "juin" => "June",
            "juillet" => "July",
            "août" => "August",
            "septembre" => "September",
            "octobre" => "October",
            "novembre" => "November",
            "décembre" => "December",
        );

        $dateString = str_replace(array_keys($monthNames), array_values($monthNames), $dateTime);
        return \DateTime::createFromFormat($dateFormat, $dateString);
    }

    public function nextPage(string $page, int $nextPage): string
    {
        return preg_replace('/(\d+)-(\d+)-(\d+)-(\d+)-(\d+)-(\d+)-(\d+)-*/', "$1-$2-$3-$nextPage-$5-$6-$7-", $page);
    }
}
