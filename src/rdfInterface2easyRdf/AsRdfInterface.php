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
use rdfInterface\LiteralInterface;
use rdfInterface\NamedNodeInterface;
use rdfInterface\BlankNodeInterface;
use rdfInterface\QuadInterface;
use rdfInterface\NodeInterface;
use rdfInterface\DatasetInterface;
use rdfInterface\DataFactoryInterface;
use rdfInterface\QuadIteratorInterface;
use rdfHelpers\GenericQuadIterator;

/**
 * Description of AsRdfInterface
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
        if ($source instanceof Graph || $source instanceof Graph && count($source->properties($resource) > 0)) {
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
     * @param Dataset|callable $dataset either an rdfInterface\DatasetInterface object
     *   or a callable creating it when being called with no parameters
     * @return DatasetInterface
     */
    static public function add(Graph | Resource $source,
                               DataFactoryInterface $dataFactory,
                               DatasetInterface | callable $dataset): DatasetInterface {
        if (is_callable($dataset)) {
            $dataset = $dataset();
        }
        if (!($dataset instanceof DatasetInterface)) {
            throw new ConverterException('If the $dataset parameter is a callable, it has to create an object of class ' . DatasetInterface::class);
        }
        $dataset->add(self::asQuadIterator($source, $dataFactory));
        return $dataset;
    }

    /**
     * Converts given EasyRdf\Resource to rdfInterface\NodeInterface.
     * 
     * @param Resource $source
     * @param DataFactoryInterface $dataFactory
     * @param NodeInterface|callable $node either an rdfInterface\NodeInterface object
     *   or a callable creating it once being called with the node's term passed as a first parameter
     * @return NodeInterface
     * @throws ConverterException
     */
    static public function asNode(Resource $source,
                                  DataFactoryInterface $dataFactory,
                                  NodeInterface | callable $node): NodeInterface {
        $term = self::asTerm($source, $dataFactory);
        if (is_callable($dataset)) {
            $node = $node($term);
        } else {
            $node = $node->withTerm($term);
        }
        if (!($dataset instanceof NodeInterface)) {
            throw new ConverterException('If the $node parameter is a callable, it has to create an object of class ' . NodeInterface::class);
        }
        $dataset = $node->getDataset();
        $dataset->add(self::asQuadIterator($source, $dataFactory));
        return $node;
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
            return self::asRdfInterfaceLiteral($source, $dataFactory);
        } elseif ($source instanceof Resource) {
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
            return $dataFactory::literal($source->getValue(), $source->getLang(), $source->getDatatype());
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
     * @param Resource|string $source
     * @param DataFactoryInterface $dataFactory
     * @return QuadInterface
     */
    static public function asQuad(Resource | string $source,
                                  DataFactoryInterface $dataFactory): QuadInterface {
        $sbj  = $dataFactory::namedNode($source->getUri());
        $pred = reset($source->propertyUris());
        return $dataFactory::quad($sbj, $dataFactory::namedNode($pred), $source->get($pred));
    }

    static private function asQuadGenerator(Graph | Resource $source,
                                            DataFactoryInterface $dataFactory): Generator {
        $graph = !empty($source->getUri()) ? $dataFactory::namedNode($source->getUri()) : null;
        foreach ($source->resources() as $res) {
            $sbj = $res->isBNode() ? $dataFactory::blankNode($res->getUri()) : $dataFactory::namedNode($res->getUri());
            foreach ($res->propertyUris() as $prop) {
                $pred = $dataFactory::namedNode($prop);
                foreach ($res->all($prop) as $obj) {
                    $obj = self::asRdfInterfaceTerm($source, $dataFactory);
                    yield $dataFactory::quad($sbj, $pred, $obj, $graph);
                }
            }
        }
    }
}
