<?php

/*
 * The MIT License
 *
 * Copyright 2023 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace rdfInterface2easyRdf;

use EasyRdf\Literal as erLiteral;
use EasyRdf\Resource;
use EasyRdf\Graph;
use quickRdf\DataFactory;
use quickRdf\BlankNode;
use quickRdf\NamedNode;
use quickRdf\Literal;
use quickRdf\Quad;
use quickRdf\Dataset;
use rdfHelpers\GenericQuadIterator;
use rdfHelpers\DatasetNode;
use rdfInterface2easyRdf\AsRdfInterface;

/**
 * Description of AsRdfInterfaceTest
 *
 * @author zozlak
 */
class AsRdfInterfaceTest extends \PHPUnit\Framework\TestCase {

    static private DataFactory $df;

    static public function setUpBeforeClass(): void {
        self::$df = new DataFactory();
    }

    public function testAsLiteral(): void {
        $lit = new erLiteral('foo');
        $tmp = AsRdfInterface::asLiteral($lit, self::$df);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals('foo', $tmp->getValue());
        $this->assertNull($tmp->getLang());
        $this->assertEquals('http://www.w3.org/2001/XMLSchema#string', $tmp->getDatatype());

        $lit = new erLiteral('literal', 'en');
        $tmp = AsRdfInterface::asLiteral($lit, self::$df);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals('literal', $tmp->getValue());
        $this->assertEquals('en', $tmp->getLang());
        $this->assertEquals('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString', $tmp->getDatatype());

        $lit = new erLiteral(1, null, 'http://www.w3.org/2001/XMLSchema#integer');
        $tmp = AsRdfInterface::asLiteral($lit, self::$df);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals('1', $tmp->getValue());
        $this->assertNull($tmp->getLang());
        $this->assertEquals('http://www.w3.org/2001/XMLSchema#integer', $tmp->getDatatype());
    }

    public function testAsBlankNode(): void {
        $graph = new Graph();
        $blank = $graph->resource('_:blank');
        $tmp   = AsRdfInterface::asBlankNode($blank, self::$df);
        $this->assertInstanceOf(BlankNode::class, $tmp);
        $this->assertEquals('_:blank', $tmp->getValue());

        $blank->addLiteral('http://bar', 'baz');
        $tmp = AsRdfInterface::asBlankNode($blank, self::$df);
        $this->assertInstanceOf(BlankNode::class, $tmp);
        $this->assertEquals('_:blank', $tmp->getValue());
    }

    public function testAsNamedNode(): void {
        $graph = new Graph();
        $res   = $graph->resource('http://foo');
        $tmp   = AsRdfInterface::asNamedNode($res, self::$df);
        $this->assertInstanceOf(NamedNode::class, $tmp);
        $this->assertEquals('http://foo', $tmp->getValue());

        $res->addLiteral('http://bar', 'baz');
        $tmp = AsRdfInterface::asNamedNode($res, self::$df);
        $this->assertInstanceOf(NamedNode::class, $tmp);
        $this->assertEquals('http://foo', $tmp->getValue());
    }

    public function testAsTerm(): void {
        $graph = new Graph();

        $lit = new erLiteral('foo');
        $tmp = AsRdfInterface::asTerm($lit, self::$df);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals('foo', $tmp->getValue());
        $this->assertNull($tmp->getLang());
        $this->assertEquals('http://www.w3.org/2001/XMLSchema#string', $tmp->getDatatype());

        $blank = $graph->resource('_:blank');
        $tmp   = AsRdfInterface::asTerm($blank, self::$df);
        $this->assertInstanceOf(BlankNode::class, $tmp);
        $this->assertEquals('_:blank', $tmp->getValue());

        $blank->addLiteral('http://bar', 'baz');
        $tmp = AsRdfInterface::asTerm($blank, self::$df);
        $this->assertInstanceOf(BlankNode::class, $tmp);
        $this->assertEquals('_:blank', $tmp->getValue());

        $res = $graph->resource('http://foo');
        $tmp = AsRdfInterface::asTerm($res, self::$df);
        $this->assertInstanceOf(NamedNode::class, $tmp);
        $this->assertEquals('http://foo', $tmp->getValue());

        $res->addLiteral('http://bar', 'baz');
        $tmp = AsRdfInterface::asTerm($res, self::$df);
        $this->assertInstanceOf(NamedNode::class, $tmp);
        $this->assertEquals('http://foo', $tmp->getValue());
    }

    public function testAsQuad(): void {
        $graph = new Graph('http://source');
        $res   = $graph->resource('http://foo');
        try {
            $tmp = AsRdfInterface::asQuad($res, self::$df);
            $this->assertTrue(false);
        } catch (ConverterException $e) {
            $this->assertEquals("EasyRdf\Resource contains no triples, therfore can't be converted to a quad", $e->getMessage());
        }

        $res->addLiteral('http://bar', 'baz');
        $tmp = AsRdfInterface::asQuad($res, self::$df);
        $this->assertInstanceOf(Quad::class, $tmp);
        $this->assertInstanceOf(NamedNode::class, $tmp->getSubject());
        $this->assertEquals('http://foo', $tmp->getSubject()->getValue());
        $this->assertInstanceOf(NamedNode::class, $tmp->getPredicate());
        $this->assertEquals('http://bar', $tmp->getPredicate()->getValue());
        $this->assertInstanceOf(Literal::class, $tmp->getObject());
        $this->assertEquals('baz', $tmp->getObject()->getValue());
        $this->assertEquals('http://source', $tmp->getGraph()->getValue());

        $res->addLiteral('http://baz', 'bar');
        $tmp = AsRdfInterface::asQuad($res, self::$df);
        $this->assertInstanceOf(Quad::class, $tmp);
        $this->assertInstanceOf(NamedNode::class, $tmp->getSubject());
        $this->assertEquals('http://foo', $tmp->getSubject()->getValue());
    }

    public function testAsQuadIterator(): void {
        $graph = new Graph('http://source');
        $tmp   = AsRdfInterface::asQuadIterator($graph, self::$df);
        $this->assertInstanceOf(GenericQuadIterator::class, $tmp);
        $this->assertCount(0, iterator_to_array($tmp));

        $res = $graph->resource('http://foo');
        $tmp = AsRdfInterface::asQuadIterator($graph, self::$df);
        $this->assertInstanceOf(GenericQuadIterator::class, $tmp);
        $this->assertCount(0, iterator_to_array($tmp));
        $tmp = AsRdfInterface::asQuadIterator($res, self::$df);
        $this->assertInstanceOf(GenericQuadIterator::class, $tmp);
        $this->assertCount(0, iterator_to_array($tmp));

        $quad1 = self::$df::quad(self::$df::namedNode('http://baz'), self::$df::namedNode('http://literalProp'), self::$df::literal('literal'), self::$df::namedNode('http://source'));
        $graph->resource('http://baz')->addLiteral('http://literalProp', 'literal');
        $tmp   = AsRdfInterface::asQuadIterator($graph, self::$df);
        $this->assertInstanceOf(GenericQuadIterator::class, $tmp);
        $tmp   = iterator_to_array($tmp);
        $this->assertCount(1, $tmp);
        $this->assertTrue($quad1->equals($tmp[0]));
        $tmp   = AsRdfInterface::asQuadIterator($res, self::$df);
        $this->assertInstanceOf(GenericQuadIterator::class, $tmp);
        $this->assertCount(0, iterator_to_array($tmp));

        $quad2 = self::$df::quad(self::$df::namedNode('http://foo'), self::$df::namedNode('http://bar'), self::$df::literal('baz'), self::$df::namedNode('http://source'));
        $res->addLiteral('http://bar', 'baz');
        $tmp   = AsRdfInterface::asQuadIterator($graph, self::$df);
        $this->assertInstanceOf(GenericQuadIterator::class, $tmp);
        $tmp   = iterator_to_array($tmp);
        $this->assertCount(2, $tmp);
        $this->assertTrue($quad1->equals($tmp[0]) && $quad2->equals($tmp[1]) || $quad1->equals($tmp[1]) && $quad2->equals($tmp[0]));
        $tmp   = AsRdfInterface::asQuadIterator($res, self::$df);
        $this->assertInstanceOf(GenericQuadIterator::class, $tmp);
        $tmp   = iterator_to_array($tmp);
        $this->assertCount(1, $tmp);
        $this->assertTrue($quad2->equals($tmp[0]));
    }

    public function testAsRdfInterface(): void {
        $df    = new DataFactory();
        $graph = new Graph();
        $blank = $graph->resource('_:blank');
        $res1  = $graph->resource('http://foo');
        $res2  = $graph->resource('http://baz');
        $res1->add('http://resource', $res2);
        $lit1  = new erLiteral('literal', 'en');
        $lit2  = new erLiteral(1, null, 'http://www.w3.org/2001/XMLSchema#integer');
        $res1->addLiteral('http://langLiteral', $lit1);
        $res1->addLiteral('http://intLiteral', $lit2);

        $tmp = AsRdfInterface::asRdfInterface($blank, self::$df);
        $this->assertInstanceOf(BlankNode::class, $tmp);
        $this->assertEquals('_:blank', $tmp->getValue());

        $tmp = AsRdfInterface::asRdfInterface($res1, self::$df);
        $this->assertInstanceOf(GenericQuadIterator::class, $tmp);
        $tmp = iterator_to_array($tmp);
        $this->assertCount(3, $tmp);
        foreach ($tmp as $i) {
            $s         = $i->getSubject();
            $this->assertInstanceOf(NamedNode::class, $s);
            $this->assertEquals('http://foo', $s->getValue());
            $p         = $i->getPredicate();
            $this->assertInstanceOf(NamedNode::class, $p);
            $p         = $p->getValue();
            $o         = $i->getObject();
            $matchRes2 = $p === 'http://resource' && $o instanceof NamedNode && $o->getValue() === 'http://baz';
            $matchLit1 = $p === 'http://langLiteral' && $o instanceof Literal && $o->getValue() === 'literal' && $o->getLang() === 'en' && $o->getDatatype() === 'http://www.w3.org/1999/02/22-rdf-syntax-ns#langString';
            $matchLit2 = $p === 'http://intLiteral' && $o instanceof Literal && $o->getValue() === '1' && $o->getLang() === null && $o->getDatatype() === 'http://www.w3.org/2001/XMLSchema#integer';
            $this->assertTrue($matchRes2 || $matchLit1 || $matchLit2);
        }


        $tmp = AsRdfInterface::asRdfInterface($res2, self::$df);
        $this->assertInstanceOf(NamedNode::class, $tmp);
        $this->assertEquals('http://baz', $tmp->getValue());

        $tmp = AsRdfInterface::asRdfInterface($lit1, self::$df);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals('literal', $tmp->getValue());
        $this->assertEquals('en', $tmp->getLang());
        $this->assertEquals('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString', $tmp->getDatatype());

        $tmp = AsRdfInterface::asRdfInterface($lit2, self::$df);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals('1', $tmp->getValue());
        $this->assertNull($tmp->getLang());
        $this->assertEquals('http://www.w3.org/2001/XMLSchema#integer', $tmp->getDatatype());

        $tmp = AsRdfInterface::asRdfInterface($graph, self::$df);
        $this->assertInstanceOf(GenericQuadIterator::class, $tmp);
    }

    public function testAdd(): void {
        $graph = new Graph('http://source');
        $res1  = $graph->resource('http://foo');
        $res1->addLiteral('http://baz', 'literal');
        $res2  = $graph->resource('http://bar');
        $res2->addResource('http://baz', 'http://resource');
        $quad1 = self::$df::quad(self::$df::namedNode('http://foo'), self::$df::namedNode('http://baz'), self::$df::literal('literal'), self::$df::namedNode('http://source'));
        $quad2 = self::$df::quad(self::$df::namedNode('http://bar'), self::$df::namedNode('http://baz'), self::$df::namedNode('http://resource'), self::$df::namedNode('http://source'));

        $dataset = AsRdfInterface::add($graph, self::$df, new Dataset());
        $this->assertInstanceOf(Dataset::class, $dataset);
        $triples = iterator_to_array($dataset->getIterator());
        $this->assertCount(2, $triples);
        $this->assertTrue($quad1->equals($triples[0]) && $quad2->equals($triples[1]) || $quad1->equals($triples[1]) && $quad2->equals($triples[0]));

        $dataset = AsRdfInterface::add($res1, self::$df, new Dataset());
        $this->assertInstanceOf(Dataset::class, $dataset);
        $triples = iterator_to_array($dataset->getIterator());
        $this->assertCount(1, $triples);
        $this->assertTrue($quad1->equals($triples[0]));
        
        $datasetNode  = AsRdfInterface::add($res1, self::$df, fn($x) => new DatasetNode(new Dataset(), $x));
        $this->assertInstanceOf(DatasetNode::class, $datasetNode);
        $this->assertTrue($quad1->getSubject()->equals($datasetNode->getNode()));
        $resTriples   = iterator_to_array($datasetNode->getIterator());
        $this->assertCount(1, $resTriples);
        $this->assertTrue($quad1->equals($resTriples[0]));
        $graphTriples = iterator_to_array($datasetNode->getDataset()->getIterator());
        $this->assertCount(2, $graphTriples);
        $this->assertTrue($quad1->equals($graphTriples[0]) && $quad2->equals($graphTriples[1]) || $quad1->equals($graphTriples[1]) && $quad2->equals($graphTriples[0]));

        $datasetNode  = AsRdfInterface::add($res1, self::$df, new DatasetNode(new Dataset(), self::$df::blankNode()), false);
        $this->assertInstanceOf(DatasetNode::class, $datasetNode);
        $this->assertTrue($quad1->getSubject()->equals($datasetNode->getNode()));
        $resTriples   = iterator_to_array($datasetNode->getIterator());
        $this->assertCount(1, $resTriples);
        $this->assertTrue($quad1->equals($resTriples[0]));
        $graphTriples = iterator_to_array($datasetNode->getDataset()->getIterator());
        $this->assertCount(1, $graphTriples);
        $this->assertTrue($quad1->equals($graphTriples[0]));
    }

    public function testAddDatasetGraph(): void {
        $graph = new Graph('http://source');
        $res1  = $graph->resource('http://foo');
        $res1->addLiteral('http://baz', 'literal');
        $res2  = $graph->resource('http://bar');
        $res2->addResource('http://baz', 'http://resource');
        $quad1 = self::$df::quad(self::$df::namedNode('http://foo'), self::$df::namedNode('http://baz'), self::$df::literal('literal'), self::$df::namedNode('http://source'));
        $quad2 = self::$df::quad(self::$df::namedNode('http://bar'), self::$df::namedNode('http://baz'), self::$df::namedNode('http://resource'), self::$df::namedNode('http://source'));

        $dataset = AsRdfInterface::addDataset($graph, self::$df, new Dataset());
        $this->assertInstanceOf(Dataset::class, $dataset);
        $triples = iterator_to_array($dataset->getIterator());
        $this->assertCount(2, $triples);
        $this->assertTrue($quad1->equals($triples[0]) && $quad2->equals($triples[1]) || $quad1->equals($triples[1]) && $quad2->equals($triples[0]));

        $dataset = AsRdfInterface::addDataset($graph, self::$df, fn() => new Dataset());
        $this->assertInstanceOf(Dataset::class, $dataset);
        $triples = iterator_to_array($dataset->getIterator());
        $this->assertCount(2, $triples);
        $this->assertTrue($quad1->equals($triples[0]) && $quad2->equals($triples[1]) || $quad1->equals($triples[1]) && $quad2->equals($triples[0]));
    }

    public function testAddDatasetResource(): void {
        $graph = new Graph();
        $res1  = $graph->resource('http://foo');
        $res1->addLiteral('http://baz', 'literal');
        $res2  = $graph->resource('http://bar');
        $res2->addResource('http://baz', 'resource');
        $quad1 = self::$df::quad(self::$df::namedNode('http://foo'), self::$df::namedNode('http://baz'), self::$df::literal('literal'));
        $quad2 = self::$df::quad(self::$df::namedNode('http://bar'), self::$df::namedNode('http://baz'), self::$df::namedNode('resource'));

        $dataset = AsRdfInterface::addDataset($res1, self::$df, new Dataset());
        $this->assertInstanceOf(Dataset::class, $dataset);
        $triples = iterator_to_array($dataset->getIterator());
        $this->assertCount(1, $triples);

        $dataset = AsRdfInterface::addDataset($res1, self::$df, fn() => new Dataset());
        $this->assertInstanceOf(Dataset::class, $dataset);
        $triples = iterator_to_array($dataset->getIterator());
        $this->assertCount(1, $triples);
        $this->assertTrue($quad1->equals($triples[0]));

        $dataset = AsRdfInterface::addDataset($res1, self::$df, new Dataset(), true);
        $this->assertInstanceOf(Dataset::class, $dataset);
        $triples = iterator_to_array($dataset->getIterator());
        $this->assertCount(2, $triples);
        $this->assertTrue($quad1->equals($triples[0]) && $quad2->equals($triples[1]) || $quad1->equals($triples[1]) && $quad2->equals($triples[0]));
    }

    public function testAddDatasetNode(): void {
        $graph = new Graph();
        $res1  = $graph->resource('http://foo');
        $res1->addLiteral('http://baz', 'literal');
        $res2  = $graph->resource('http://bar');
        $res2->addResource('http://baz', 'resource');
        $quad1 = self::$df::quad(self::$df::namedNode('http://foo'), self::$df::namedNode('http://baz'), self::$df::literal('literal'));
        $quad2 = self::$df::quad(self::$df::namedNode('http://bar'), self::$df::namedNode('http://baz'), self::$df::namedNode('resource'));

        $datasetNode  = AsRdfInterface::addDatasetNode($res1, self::$df, new DatasetNode(new Dataset(), self::$df::blankNode()));
        $this->assertInstanceOf(DatasetNode::class, $datasetNode);
        $this->assertTrue($quad1->getSubject()->equals($datasetNode->getNode()));
        $resTriples   = iterator_to_array($datasetNode->getIterator());
        $this->assertCount(1, $resTriples);
        $this->assertTrue($quad1->equals($resTriples[0]));
        $graphTriples = iterator_to_array($datasetNode->getDataset()->getIterator());
        $this->assertCount(2, $graphTriples);
        $this->assertTrue($quad1->equals($graphTriples[0]) && $quad2->equals($graphTriples[1]) || $quad1->equals($graphTriples[1]) && $quad2->equals($graphTriples[0]));

        $dataset      = AsRdfInterface::addDatasetNode($res1, self::$df, fn($x) => new DatasetNode(new Dataset(), $x));
        $this->assertInstanceOf(DatasetNode::class, $datasetNode);
        $this->assertTrue($quad1->getSubject()->equals($datasetNode->getNode()));
        $resTriples   = iterator_to_array($datasetNode->getIterator());
        $this->assertCount(1, $resTriples);
        $this->assertTrue($quad1->equals($resTriples[0]));
        $graphTriples = iterator_to_array($datasetNode->getDataset()->getIterator());
        $this->assertCount(2, $graphTriples);
        $this->assertTrue($quad1->equals($graphTriples[0]) && $quad2->equals($graphTriples[1]) || $quad1->equals($graphTriples[1]) && $quad2->equals($graphTriples[0]));

        $datasetNode  = AsRdfInterface::addDatasetNode($res1, self::$df, new DatasetNode(new Dataset(), self::$df::blankNode()), false);
        $this->assertInstanceOf(DatasetNode::class, $datasetNode);
        $this->assertTrue($quad1->getSubject()->equals($datasetNode->getNode()));
        $resTriples   = iterator_to_array($datasetNode->getIterator());
        $this->assertCount(1, $resTriples);
        $this->assertTrue($quad1->equals($resTriples[0]));
        $graphTriples = iterator_to_array($datasetNode->getDataset()->getIterator());
        $this->assertCount(1, $graphTriples);
        $this->assertTrue($quad1->equals($graphTriples[0]));
    }
}
