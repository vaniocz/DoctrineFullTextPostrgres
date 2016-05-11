<?php
/**
 * @author: James Murray <jaimz@vertigolabs.org>
 * @copyright:
 * @date: 9/19/2015
 * @time: 10:11 AM
 */

namespace VertigoLabs\TsQuery;

use Base\BaseORMTestCase;
use TsVector\Fixture\Article;

class TsQueryTest extends BaseORMTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->setUpSchema([Article::class]);

        foreach ($this->articleProvider() as $articleData) {
            $article = new Article();
            $article->setTitle($articleData[0]);
            $article->setBody($articleData[1]);
            $this->em->persist($article);
        }
        $this->em->flush();
    }

    /**
     * @test
     * @dataProvider searchDataProvider
     */
    public function shouldReturnArticles($queryStr, $field, $numberFound, $assertRes)
    {
        $query = $this->em->createQuery('SELECT a FROM TsVector\\Fixture\\Article a WHERE TSQUERY(a.' . $field . ',:searchQuery) = TRUE');
        $query->setParameter('searchQuery', $queryStr);
        $result = $query->getArrayResult();

        static::assertEquals($numberFound, count($result));
    }

    /**
     * @test
     */
    public function shouldUseFieldWeights()
    {
        $query = $this->em->createQuery('SELECT a FROM TsVector\\Fixture\\Article a WHERE TSQUERY(a.fullFTS,:searchQuery) = TRUE ORDER BY TSRANK(a.fullFTS,:searchQuery) DESC');
        $query->setParameter('searchQuery', 'platypus');
        $result = $query->getArrayResult();

        static::assertEquals(2, count($result));
        static::assertEquals('Platypus is awesome', $result[0]['title']);
        static::assertEquals('Amazing animals', $result[1]['title']);

        $query->setParameter('searchQuery', 'amazing');
        $result = $query->getArrayResult();

        static::assertEquals(2, count($result));
        static::assertEquals('Amazing animals', $result[0]['title']);
        static::assertEquals('Platypus is awesome', $result[1]['title']);

        $query->setParameter('searchQuery', 'rockets');
        $result = $query->getArrayResult();
        static::assertEquals(1, count($result));
        static::assertEquals('Amazing animals', $result[0]['title']);
    }

    public function articleProvider()
    {
        return [
            [
                'Test Article One',
                'This is a test article used for running unit tests. It contains a couple keywords such as Elephant, Dolphin, Kitten, and Baboon.'
            ],
            [
                'Baboons Invade Seaworld',
                'In a crazy turn of events a pack a rabid red baboons invade Seaworld. Officials say that the Dolphins are being held hostage'
            ],
            [
                'Elephants learn to fly',
                'Yesterday several witnesses claim to have seen a seen a number of purple elephants flying through the sky over the downtown area.'
            ],
            [
                'Giraffes Shorter Than Experts Though',
                'A recent study has shown that giraffes are actually much shorter than researchers previously believed. "What we didn\'t realize was that they were actually always surrounded by other really short object" says one official.'
            ],
            [
                'Green Kittens not as cute as believed',
                'A recent uncovering of an underground "cat mafia" has found new evidence that the media is being paid to over-exaggerate the cuteness of kittens who have been painted green.'
            ],
            [
                'Test Article Two',
                'This is another test article used for running unit tests. This article has color based keywords such as; Blue, Green, Red, and Yellow.'
            ],
            [
                'Platypus is awesome',
                'About amazing animals'
            ],
            [
                'Amazing animals',
                'About platypus. Platupus is amazing. Rocket.'
            ],
        ];
    }

    public function searchDataProvider()
    {
        return [
            ['dolphins', 'body', 2, true],
            ['Dolphins', 'body', 2, true],
            ['Dolphins', 'title', 0, false],
            ['Dolphins | seaworld', 'title', 1, false],
            ['Dolphins | seaworld', 'body', 2, false],
        ];
    }
}
