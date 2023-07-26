<?php

/*
 * The MIT License
 *
 * Copyright 2021 zozlak.
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

use quickRdf\DataFactory as DF;
use quickRdf\Dataset;
use quickRdf\DatasetNode;
use rdfInterface2easyRdf\AsEasyRdf;
use EasyRdf\Literal;
use EasyRdf\Resource;
use EasyRdf\Graph;

/**
 *
 * @author zozlak
 */
class AsEasyRdfTest extends \PHPUnit\Framework\TestCase {

    public function testAsEasyRdf(): void {
        $blank    = DF::blankNode();
        $named    = DF::namedNode('http://foo');
        $literal1 = DF::literal('bar');
        $literal2 = DF::literal('bar', 'en');
        $literal3 = DF::literal('1', null, 'http://www.w3.org/2001/XMLSchema#integer');
        $quad     = DF::quad($blank, $named, $literal2);
        $dataset  = new Dataset();
        $dataset->add($quad);
        $node     = DatasetNode::factory($named)->withDataset($dataset);

        $tmp = AsEasyRdf::asEasyRdf($blank);
        $this->assertInstanceOf(Resource::class, $tmp);
        $this->assertTrue($tmp->isBNode());

        $tmp = AsEasyRdf::asEasyRdf($named);
        $this->assertInstanceOf(Resource::class, $tmp);
        $this->assertFalse($tmp->isBNode());
        $this->assertEquals($named->getValue(), $tmp->getUri());

        $tmp = AsEasyRdf::asEasyRdf($literal1);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals($literal1->getValue(), $tmp->getValue());
        $this->assertNull($tmp->getLang());
        $this->assertEquals('xsd:string', $tmp->getDatatype());

        $tmp = AsEasyRdf::asEasyRdf($literal2);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals($literal2->getValue(), $tmp->getValue());
        $this->assertEquals('en', $tmp->getLang());
        $this->assertNull($tmp->getDatatype());

        $tmp = AsEasyRdf::asEasyRdf($literal3);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals($literal3->getValue(), $tmp->getValue());
        $this->assertEmpty($tmp->getLang());
        $this->assertEquals('xsd:integer', $tmp->getDatatype());

        $tmp  = AsEasyRdf::asEasyRdf($quad);
        $prop = $tmp->getGraph()->resource('http://foo');
        $this->assertInstanceOf(Resource::class, $tmp);
        $this->assertEquals('bar', $tmp->get($prop)->getValue());
        $this->assertEquals('en', $tmp->get($prop)->getLang());

        $tmp  = AsEasyRdf::asEasyRdf($node);
        $prop = $tmp->getGraph()->resource('http://foo');
        $this->assertInstanceOf(Resource::class, $tmp);
        $this->assertCount(0, $tmp->properties());

        $tmp = AsEasyRdf::asEasyRdf($dataset);
        $this->assertInstanceOf(Graph::class, $tmp);
        $res = $tmp->resources();
        $this->assertCount(1, $res);
        $this->assertTrue(reset($res)->isBNode());

        $graph = new Graph();
        $graph->resource('http://baz')->addLiteral('https://foo', 'other value');
        AsEasyRdf::asEasyRdf($quad, $graph);
        $res   = $graph->resources();
        $this->assertCount(2, $res);
    }

    public function testAsLiteral(): void {
        $literal1 = DF::literal('bar');

        $tmp = AsEasyRdf::asLiteral($literal1);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals($literal1->getValue(), $tmp->getValue());
        $this->assertNull($tmp->getLang());
        $this->assertEquals('xsd:string', $tmp->getDatatype());
    }

    public function testAsResource(): void {
        $blank    = DF::blankNode();
        $named    = DF::namedNode('http://foo');
        $literal2 = DF::literal('bar', 'en');
        $dataset  = new Dataset();
        $dataset->add(DF::quad($blank, $named, $literal2));
        $node     = DatasetNode::factory($named)->withDataset($dataset);

        $tmp = AsEasyRdf::asResource($blank);
        $this->assertInstanceOf(Resource::class, $tmp);
        $this->assertTrue($tmp->isBNode());

        $tmp = AsEasyRdf::asResource($named);
        $this->assertInstanceOf(Resource::class, $tmp);
        $this->assertFalse($tmp->isBNode());
        $this->assertEquals($named->getValue(), $tmp->getUri());

        $tmp  = AsEasyRdf::asResource($node);
        $prop = $tmp->getGraph()->resource('http://foo');
        $this->assertInstanceOf(Resource::class, $tmp);
        $this->assertCount(0, $tmp->properties());
    }

    public function testAsGraph(): void {
        $blank    = DF::blankNode();
        $named    = DF::namedNode('http://foo');
        $literal2 = DF::literal('bar', 'en');
        $dataset  = new Dataset();
        $dataset->add(DF::quad($blank, $named, $literal2));
        $node     = DatasetNode::factory($named)->withDataset($dataset);

        $tmp = AsEasyRdf::asGraph($node);
        $this->assertInstanceOf(Graph::class, $tmp);
        $res = $tmp->resources();
        $this->assertCount(2, $res);
        $this->assertTrue(reset($res)->isBNode());

        $tmp = AsEasyRdf::asGraph($dataset);
        $this->assertInstanceOf(Graph::class, $tmp);
        $res = $tmp->resources();
        $this->assertCount(1, $res);
        $this->assertTrue(reset($res)->isBNode());
    }
}
