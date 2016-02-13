<?php

namespace FS\SolrBundle\Tests\Query;

use FS\SolrBundle\Doctrine\Annotation\AnnotationReader;
use FS\SolrBundle\Query\SolrQuery;
use FS\SolrBundle\SolrQueryFacade;

/**
 *
 * @group query
 */
class SolrQueryTest extends \PHPUnit_Framework_TestCase
{

    private function getFieldMapping()
    {
        return array(
            'title_s' => 'title',
            'text_t' => 'text',
            'created_at_dt' => 'created_at'
        );
    }

    /**
     * @return SolrQuery
     */
    private function createQueryWithFieldMapping()
    {
        $solr = $this->getMock('FS\SolrBundle\Solr', array(), array(), '', false);

        $solrQuery = new SolrQuery();
        $solrQuery->setSolr($solr);
        $solrQuery->setMappedFields($this->getFieldMapping());

        return $solrQuery;
    }

    /**
     * @return SolrQuery
     */
    private function createQueryWithSearchTerms()
    {
        $query = $this->createQueryWithFieldMapping();

        $query->addSearchTerm('title', 'foo')
            ->addSearchTerm('text', 'bar');

        return $query;
    }

    public function testAddField_AllFieldsAreMapped()
    {
        $solrQuery = $this->createQueryWithFieldMapping();

        $solrQuery->addField('title')
            ->addField('text');

        $fields = $solrQuery->getFields();

        $this->assertEquals(2, count($fields));
        $this->assertTrue(in_array('title_s', $fields));
        $this->assertTrue(in_array('text_t', $fields));
    }

    public function testAddField_OneFieldOfTwoNotMapped()
    {
        $solrQuery = $solrQuery = $this->createQueryWithFieldMapping();

        $solrQuery->addField('title')
            ->addField('foo');

        $fields = $solrQuery->getFields();

        $this->assertEquals(1, count($fields));
        $this->assertTrue(in_array('title_s', $fields));
    }

    public function testGetSolrQuery_QueryTermShouldCorrect()
    {
        $expected = 'title_s:foo OR text_t:bar';

        $query = $this->createQueryWithSearchTerms();

        $this->assertEquals($expected, $query->getQuery());

    }

    public function testAddSearchTerm_AllFieldsAreMapped()
    {
        $solrQuery = $this->createQueryWithFieldMapping();

        $solrQuery->addSearchTerm('title', 'foo')
            ->addSearchTerm('text', 'bar');

        $terms = $solrQuery->getSearchTerms();

        $this->assertTrue(array_key_exists('title_s', $terms), 'title_s not in terms');
        $this->assertTrue(array_key_exists('text_t', $terms), 'text_t not in terms');
    }

    public function testAddSearchTerm_OneFieldOfTwoNotMapped()
    {
        $solrQuery = $this->createQueryWithFieldMapping();

        $solrQuery->addSearchTerm('title', 'foo')
            ->addSearchTerm('foo', 'bar');

        $terms = $solrQuery->getSearchTerms();

        $this->assertTrue(array_key_exists('title_s', $terms), 'title_s not in terms');
        $this->assertEquals(1, count($terms));
    }

    public function testAddSearchTerm_UnknownField()
    {
        $solrQuery = $this->createQueryWithFieldMapping();

        $solrQuery->addSearchTerm('unknownfield', 'foo');

        $terms = $solrQuery->getSearchTerms();

        $this->assertEquals(0, count($terms));
    }

    public function testGetQuery_TermsConcatWithOr()
    {
        $expected = 'title_s:foo OR text_t:bar';

        $query = $this->createQueryWithSearchTerms();

        $this->assertEquals($expected, $query->getQuery());
    }

    public function testGetQuery_TermsConcatWithAnd()
    {
        $expected = 'title_s:foo AND text_t:bar';

        $query = $this->createQueryWithSearchTerms();
        $query->setUseAndOperator(true);

        $this->assertEquals($expected, $query->getQuery());
    }

    public function testGetQuery_SearchInAllFields()
    {
        $solrQuery = $this->createQueryWithFieldMapping();
        $solrQuery->queryAllFields('foo');

        $expected = 'title_s:foo OR text_t:foo OR created_at_dt:foo';

        $this->assertEquals($expected, $solrQuery->getQuery());
    }

    public function testGetQuery_SurroundTermWithDoubleQuotes()
    {
        $solrQuery = $this->createQueryWithFieldMapping();
        $solrQuery->queryAllFields('foo 12');

        $expected = 'title_s:"foo 12" OR text_t:"foo 12" OR created_at_dt:"foo 12"';

        $this->assertEquals($expected, $solrQuery->getQuery());
    }

    public function testGetQuery_SurroundWildcardTermWithDoubleQuotes()
    {
        $solrQuery = $this->createQueryWithFieldMapping();
        $solrQuery->queryAllFields('foo 12');
        $solrQuery->setUseWildcard(true);

        $expected = 'title_s:"*foo 12*" OR text_t:"*foo 12*" OR created_at_dt:"*foo 12*"';

        $this->assertEquals($expected, $solrQuery->getQuery());
    }
}
