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

use Generator;
use EasyRdf\Resource;
use EasyRdf\Graph;
use EasyRdf\Literal;
use EasyRdf\RdfNamespace;
use rdfInterface\LiteralInterface;
use rdfInterface\NamedNodeInterface;
use rdfInterface\BlankNodeInterface;
use rdfInterface\QuadInterface;
use rdfInterface\DatasetInterface;
use rdfInterface\DatasetNodeInterface;
use rdfInterface\DataFactoryInterface;
use rdfInterface\QuadIteratorInterface;
use rdfHelpers\GenericQuadIterator;

/**
 * Converts EasyRdf objects into rdfInterface once.
 * 
 * Remarks:
 * 
 * - As the rdfInterface is an interface only you must always provide 
 *   a rdfInterface\DataFactoryInterface object so the converter knows how to 
 *   create rdfInterface terms
 * - As creation of rdfInterface\DatasetInterface and rdfInterface\DatasetNodeInterface
 *   objects is not covered by the rdfInterface, the asRdfInterface()
 *   can only return a set of triples as an rdfInterface\QuadIteratorInterface.
 *   If you need conversion to an rdfInterface\Dataset or rdfInterface\DatasetNodeInterface,
 *   please use one of add()/addDataset()/addDatasetNode() methods.
 * - Convertion of EasyRdf\Resource is ambiguous. It can mean an RDF term 
 *   (rdfInterface\BlankNodeInterface or rdfInterface\NamedNodeInterface) or
 *   a set of triples having the EasyRdf\Resource as a subject.
 *   asRdfInterface() method implements a simple guessing mechanism -
 *   an EasyRdf\Resource with no properties is converted to an RDF term while
 *   the one containing at least one property to an rdfInterface\QuadIteratorInterface.
 *   For the reason described in the previous point conversion of the EasyRdf\Resource
 *   to an rdfInterface\DatasetNodeInterface or rdfInterface\DatasetInterface
 *   requires dedicated methods (add()/addDataset()/addDatasetNode()). In this
 *   case one more thing to consider is should whole graph triples be included 
 *   in the resulting dataset or not (see the $wholeGraph parameter of
 *   add()/addDataset()/addDatasetNode()).
 *
 * @author zozlak
 */
class AsRdfInterface {

    /**
     * Converts EasyRdf objects to rdfInterface once using given rdfInterface\DataFactory.
     * 
     * - EasyRdf\Literal => rdfInterface\LiteralInterface
     * - blank EasyRdf\Resource => rdfInterface\BlankNode if it has no properties
     *   or rdfInterface\QuadIteratorInterface it it has at least one property
     * - not blank EasyRdf\Resource => rdfInterface\NamedNode if it has no properties
     *   or rdfInterface\QuadIteratorInterface it it has at least one property
     * - EasyRdf\Graph => rdfInterface\QuadIteratorInterface
     * 
     * @param Literal|Resource|Graph $source
     * @param DataFactoryInterface $dataFactory
     * @return LiteralInterface|BlankNodeInterface|NamedNodeInterface|QuadIteratorInterface
     */
    static public function asRdfInterface(Literal | Resource | Graph $source,
                                          DataFactoryInterface $dataFactory): LiteralInterface | BlankNodeInterface | NamedNodeInterface | QuadIteratorInterface {
        if ($source instanceof Graph || $source instanceof Resource && count($source->propertyUris()) > 0) {
            return self::asQuadIterator($source, $dataFactory);
        } else {
            return self::asTerm($source, $dataFactory);
        }
    }

    /**
     * Stronger-typed version of asRdfInterface() which always returns 
     * a rdfInterface\QuadIteratorInterface.
     * 
     * @param Graph|Resource $source
     * @param DataFactoryInterface $dataFactory
     * @return QuadIteratorInterface
     */
    static public function asQuadIterator(Graph | Resource $source,
                                          DataFactoryInterface $dataFactory): QuadIteratorInterface {
        return new GenericQuadIterator(self::asQuadGenerator($source, $dataFactory));
    }

    /**
     * Appends contents of a given EasyRdf\Resource or EasyRdf\Graph to
     * a given rdfInterface\Dataset
     * 
     * @param Graph|Resource $source
     * @param DataFactoryInterface $dataFactory
     * @param DatasetInterface|DatasetNodeInterface|callable $datasetOrNode If callable is used,
     *   calling it with should create an rdfInterface\DataseInterface object or 
     *   an rdfInterface\DatasetNodeInterface object (if $source is an EasyRdf\Resource,
     *   the callable is called with $source URI converted to rdfInterface\Term 
     *   as a first parameter).
     * @param bool|null $wholeGraph if an EasyRdf\Resource is passed as a $source, 
     *   should the whole graph be copied (true) or only triples having $source
     *   as a subject (false). The default value is true if $datasetOrNode is 
     *   rdfInterface\DatasetInstance and false is $datasetOrNode is rdfInterface\DatasetNodeInstance.
     * @return DatasetNodeInterface
     */
    static public function add(Graph | Resource $source,
                               DataFactoryInterface $dataFactory,
                               DatasetInterface | DatasetNodeInterface | callable $datasetOrNode,
                               ?bool $wholeGraph = null): DatasetInterface | DatasetNodeInterface {
        $sourceIsRes = $source instanceof Resource;
        if (is_callable($datasetOrNode)) {
            $datasetOrNode = $sourceIsRes ? $datasetOrNode(self::asTerm($source, $dataFactory)) : $datasetOrNode();
        } elseif ($datasetOrNode instanceof DatasetNodeInterface && $sourceIsRes) {
            $datasetOrNode = $datasetOrNode->withNode(self::asTerm($source, $dataFactory));
        }
        if (!($datasetOrNode instanceof DatasetInterface || $datasetOrNode instanceof DatasetNodeInterface)) {
            throw new ConverterException('If the $dataset parameter is a callable, it has to create an object of class ' . DatasetInterface::class . ' or ' . DatasetNodeInterface::class);
        }
        $dataset = $datasetOrNode instanceof DatasetNodeInterface ? $datasetOrNode->getDataset() : $datasetOrNode;
        $wholeGraph ??= $datasetOrNode instanceof DatasetNodeInterface;
        if ($sourceIsRes && $wholeGraph) {
            $source = $source->getGraph();
        }
        $dataset->add(self::asQuadIterator($source, $dataFactory));
        return $datasetOrNode;
    }

    /**
     * Strongly-typed version of add().
     * 
     * @param Graph|Resource $source
     * @param DataFactoryInterface $dataFactory
     * @param DatasetInterface|callable $dataset If callable is used,
     *   calling it with no parameters should create 
     *   an rdfInterface\DataseInterface object.
     * @param bool $wholeGraph if an EasyRdf\Resource is passed as a $source, 
     *   should the whole graph be copied (true) or only triples having $source
     *   as a subject (false).
     * @return DatasetInterface
     * @see add()
     */
    static public function addDataset(Graph | Resource $source,
                                      DataFactoryInterface $dataFactory,
                                      DatasetInterface | callable $dataset,
                                      bool $wholeGraph = false): DatasetInterface {
        return self::add($source, $dataFactory, $dataset, $wholeGraph);
    }

    /**
     * Strongly-typed version of add().
     * 
     * @param Resource $source
     * @param DataFactoryInterface $dataFactory
     * @param DatasetNodeInterface|callable $datasetNode If callable is used,
     *   calling it with an EasyRdf\Resource URI as a first parameters should create 
     *   an rdfInterface\DataseNodeInterface object.
     * @param bool $wholeGraph if an EasyRdf\Resource is passed as a $source, 
     *   should the whole graph be copied (true) or only triples having $source
     *   as a subject (false).
     * @return DatasetNodeInterface
     * @see add()
     */
    static public function addDatasetNode(Resource $source,
                                          DataFactoryInterface $dataFactory,
                                          DatasetNodeInterface | callable $datasetNode,
                                          bool $wholeGraph = true): DatasetNodeInterface {
        return self::add($source, $dataFactory, $datasetNode, $wholeGraph);
    }

    /**
     * Stronger-typed version of asRdfInterface() which always returns 
     * a single rdfInterface term.
     * 
     * It means an EasyRdf\Resource is always converted to an rdfInterface\BlankNode
     * or an rdfInterface\NamedNode.
     * 
     * @param Literal|Resource $source
     * @param DataFactoryInterface $dataFactory
     * @return LiteralInterface|BlankNodeInterface|NamedNodeInterface
     */
    static public function asTerm(Literal | Resource $source,
                                  DataFactoryInterface $dataFactory): LiteralInterface | BlankNodeInterface | NamedNodeInterface {
        if ($source instanceof Literal) {
            return self::asLiteral($source, $dataFactory);
        } else {
            return $source->isBNode() ? $dataFactory::blankNode($source->getUri()) : $dataFactory::namedNode($source->getUri());
        }
    }

    /**
     * Strongly-typed version of asRdfInterfaceTerm().
     * 
     * @param Literal|string $source
     * @param DataFactoryInterface $dataFactory
     * @return LiteralInterface
     */
    static public function asLiteral(Literal | string $source,
                                     DataFactoryInterface $dataFactory): LiteralInterface {
        if ($source instanceof Literal) {
            $datatype = $source->getDatatype();
            if (!empty($datatype)) {
                $datatype = RdfNamespace::expand($datatype);
            }
            return $dataFactory::literal($source->getValue(), $source->getLang(), $datatype);
        } else {
            return $dataFactory::literal($source);
        }
    }

    /**
     * Strongly-typed version of asRdfInterfaceTerm().
     * 
     * @param Resource|string $source
     * @param DataFactoryInterface $dataFactory
     * @return BlankNodeInterface
     */
    static public function asBlankNode(Resource | string $source,
                                       DataFactoryInterface $dataFactory): BlankNodeInterface {
        return $dataFactory::blankNode((string) $source);
    }

    /**
     * Strongly-typed version of asRdfInterfaceTerm().
     * 
     * @param Resource|string $source
     * @param DataFactoryInterface $dataFactory
     * @return NamedNodeInterface
     */
    static public function asNamedNode(Resource | string $source,
                                       DataFactoryInterface $dataFactory): NamedNodeInterface {
        return $dataFactory::namedNode((string) $source);
    }

    /**
     * Treats EasyRdf\Resource as a triple and converts it to rdfInterface\Quad
     * by taking the first property returned by the $source->propertyUris()
     * as quad's predicate and first value returned by $source->get(property)
     * as quad's object.
     * 
     * @param Resource $source
     * @param DataFactoryInterface $dataFactory
     * @return QuadInterface
     */
    static public function asQuad(Resource $source,
                                  DataFactoryInterface $dataFactory): QuadInterface {
        $graph      = $source->getGraph()->getUri();
        $graph      = empty($graph) ? null : $dataFactory::namedNode($graph);
        $sbj        = $dataFactory::namedNode($source->getUri());
        $properties = $source->propertyUris();
        $pred       = reset($properties);
        if (!is_string($pred) || empty($pred)) {
            throw new ConverterException("EasyRdf\Resource contains no triples, therfore can't be converted to a quad");
        }
        $obj = $source->get($source->getGraph()->resource($pred));
        $obj = self::asTerm($obj, $dataFactory);
        return $dataFactory::quad($sbj, $dataFactory::namedNode($pred), $obj, $graph);
    }

    static private function asQuadGenerator(Graph | Resource $source,
                                            DataFactoryInterface $dataFactory): Generator {
        $erGraph = $source instanceof Graph ? $source : $source->getGraph();

        $graph = $source instanceof Graph ? $source : $source->getGraph();
        $graph = !empty($graph->getUri()) ? $dataFactory::namedNode($graph->getUri()) : null;

        $resources = $source instanceof Graph ? $source->resources() : [$source];
        foreach ($resources as $res) {
            $sbj = self::asTerm($res, $dataFactory);
            foreach ($res->propertyUris() as $prop) {
                $pred = $dataFactory::namedNode($prop);
                foreach ($res->all($erGraph->resource($prop)) as $obj) {
                    $obj = self::asTerm($obj, $dataFactory);
                    yield $dataFactory::quad($sbj, $pred, $obj, $graph);
                }
            }
        }
    }
}
