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

        $tmp = AsRdfInterface::asRdfInterface($blank, $df);
        $this->assertInstanceOf(BlankNode::class, $tmp);
        $this->assertEquals('_:blank', $tmp->getValue());

        $tmp = AsRdfInterface::asRdfInterface($res1, $df);
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


        $tmp = AsRdfInterface::asRdfInterface($res2, $df);
        $this->assertInstanceOf(NamedNode::class, $tmp);
        $this->assertEquals('http://baz', $tmp->getValue());

        $tmp = AsRdfInterface::asRdfInterface($lit1, $df);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals('literal', $tmp->getValue());
        $this->assertEquals('en', $tmp->getLang());
        $this->assertEquals('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString', $tmp->getDatatype());

        $tmp = AsRdfInterface::asRdfInterface($lit2, $df);
        $this->assertInstanceOf(Literal::class, $tmp);
        $this->assertEquals('1', $tmp->getValue());
        $this->assertNull($tmp->getLang());
        $this->assertEquals('http://www.w3.org/2001/XMLSchema#integer', $tmp->getDatatype());

        $tmp = AsRdfInterface::asRdfInterface($graph, $df);
        $this->assertInstanceOf(GenericQuadIterator::class, $tmp);
    }
}
