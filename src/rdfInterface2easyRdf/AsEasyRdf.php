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

use EasyRdf\Resource;
use EasyRdf\Graph;
use EasyRdf\Literal;
use rdfInterface\LiteralInterface;
use rdfInterface\NamedNodeInterface;
use rdfInterface\BlankNodeInterface;
use rdfInterface\QuadInterface;
use rdfInterface\NodeInterface;
use rdfInterface\QuadIteratorInterface;
use rdfInterface\QuadIteratorAggregateInterface;

/**
 * Description of AsEasyRdf
 *
 * @author zozlak
 */
class AsEasyRdf {

    /**
     * Tries to convert any rdfInterface object to a corresponding EasyRdf one.
     * 
     * - rdfInterface\LiteralInterface => EasyRdf\Literal
     * - rdfInterface\BlankNodeInterface => EasyRdf\Resource
     * - rdfInterface\NamedNodeInterface => EasyRdf\Resource
     * - rdfInterface\QuadInterface => EasyRdf\Resource with quad's predicate and object assigned.
     *   The conversion will fail if quad's subject or object can't be represented in the EasyRdf
     *   (e.g. when they are quads).
     * - rdfInterface\DatasetInterface => EasyRdf\Graph
     * 
     * @param LiteralInterface|BlankNodeInterface|NamedNodeInterface|QuadInterface|QuadIteratorInterface|QuadIteratorAggregateInterface $source
     * @param Graph $graph EasyRdf graph to embed the converted objects into.
     *   If not provided, a new empty graph is used.
     * @return Resource
     */
    static public function asEasyRdf(LiteralInterface | BlankNodeInterface | NamedNodeInterface | QuadInterface | QuadIteratorInterface | QuadIteratorAggregateInterface $source,
                                     Graph $graph = null): mixed {
        $graph ??= new Graph();
        if ($source instanceof LiteralInterface) {
            return new Literal($source->getValue(), $source->getLang(), $source->getDatatype());
        } elseif ($source instanceof BlankNodeInterface || $source instanceof NamedNodeInterface) {
            return $graph->resource($source->getValue());
        } elseif ($source instanceof QuadInterface) {
            $res = $graph->resource($source->getSubject()->getValue());
            $res->add($source->getPredicate()->getValue(), self::asEasyRdf($source->getObject(), $graph));
            return $res;
        } elseif ($source instanceof NodeInterface) {
            try {
                $resUri = $source->getTerm()->getValue();
                foreach ($source->getDataset() as $quad) {
                    self::asEasyRdf($quad, $graph);
                }
                return $graph->resource($resUri);
            } catch (\Throwable $ex) {
                // most probably an ordinary Dataset
            }
        }
        foreach ($source as $quad) {
            self::asEasyRdf($quad, $graph);
        }
        return $graph;
    }

    /**
     * Strongly-typed version of asEasyRdfResource().
     * 
     * @param LiteralInterface $source
     * @param Graph $graph
     * @return Literal
     * @see asEasyRdf()
     */
    static public
        function asLiteral(LiteralInterface $source, Graph $graph = null): Literal {
        return self::asEasyRdf($source, $graph);
    }

    /**
     * Strongly-typed version of asEasyRdfResource().
     * 
     * @param BlankNodeInterface|NamedNodeInterface|QuadInterface|NodeInterface $source
     * @param Graph $graph
     * @return Resource
     * @see asEasyRdf()
     */
    static public
        function asResource(BlankNodeInterface | NamedNodeInterface | QuadInterface | NodeInterface $source,
                            Graph $graph = null): Resource {
        return self::asEasyRdf($source, $graph);
    }

    /**
     * Strongly-typed version of asEasyRdfResource().
     * 
     * @param NodeInterface|QuadIteratorInterface|QuadIteratorAggregateInterface $source
     * @param Graph $graph
     * @return Graph
     * @see asEasyRdf()
     */
    static public
        function asGraph(NodeInterface | QuadIteratorInterface | QuadIteratorAggregateInterface $source,
                         Graph $graph = null): Graph {
        $graph = self::asEasyRdf($source, $graph);
        return $graph instanceof Resource ? $graph->getGraph() : $graph;
    }
}
