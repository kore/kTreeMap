<?php

/**
 * Tree map renderer
 *
 * Configure the rendering using the closures which define the object
 * properties. Each closure receives the current width of the element, and the
 * subtree it curently renders. The closures to set are:
 *
 * - cellColor
 * - border
 * - padding
 * - textProperties
 *
 * An usage example is shown below:
 *
 * <code>
 *  $renderer = new kTreeMap();
 *  $renderer->cellColor = function ( $percent, $subtree )
 *  {
 *      if ( is_array( $subtree ) )
 *      {
 *          return '#eeeeef';
 *      }
 *
 *      $value = min( 1, $subtree / 20 );
 *      return sprintf( "#%02x%02x%02x", 115 + $value * 89, 210 * ( 1 - $value ), 22 * ( 1 - $value ) );
 *  };
 *
 *  $svg = $renderer->render( array(
 *      array( 'Foo' => 34 ),
 *      array(
 *          array( 'Bar' => 12 ),
 *          array( 'Baz' => 8 ),
 *      ),
 *      array(
 *          array( 'Bar' => 18 ),
 *          array(
 *              array( 'Bar' => 12 ),
 *              array( 'Baz' => 4 ),
 *          ),
 *      ),
 *  ), 500, 300 );
 *  $svg->save( 'treemap.svg' );
 * </code>
 * 
 * @package Webdav
 * @version //autogen//
 * @copyright Copyright (C) 2005-2007 eZ systems as. All rights reserved.
 * @author  
 * @license http://ez.no/licenses/new_bsd New BSD License
 */
class kTreeMap 
{
    /**
     * Closure calculating the cell color
     * 
     * @var Closure
     */
    protected $cellColor;

    /**
     * Closure calculating the border stye definition
     * 
     * @var Closure
     */
    protected $border;

    /**
     * Closure calculating the inner cell padding
     * 
     * @var Closure
     */
    protected $padding;

    /**
     * Closure calculating the text style properties
     * 
     * @var Closure
     */
    protected $textProperties;

    /**
     * Construct tree map
     * 
     * @return void
     */
    public function __construct()
    {
        $this->cellColor = function ( $percent, $subTree )
        {
            return '#eeeeef';
        };

        $this->border = function ( $percent, $subTree )
        {
            return 'stroke-width: 1; stroke: #babdb6';
        };

        $this->padding = function ( $percent, $subTree )
        {
            return 2;
        };

        $this->textProperties = function ( $percent, $subTree )
        {
            return 'font-size: 14px; font-style: sans-serif; fill: #ffffff;';
        };
    }

    /**
     * Set closure
     * 
     * @param string $property 
     * @param Closure $closure 
     * @return void
     */
    public function __set( $property, Closure $closure )
    {
        switch ( $property )
        {
            case 'cellColor':
            case 'border':
            case 'padding':
            case 'textProperties':
                $this->$property = $closure;
                break;
            default:
                throw new RuntimeException( "Unknown property $property." );
        }
    }

    /**
     * Render subtree in the specified dimensions
     * 
     * @param DOMElement $root
     * @param array $tree 
     * @param float $x 
     * @param float $y 
     * @param float $width 
     * @param float $height 
     * @param bool $vertical
     * @return void
     */
    protected function renderSubtree( DOMElement $root, array $tree, $x, $y, $width, $height, $vertical = false )
    {
        $reduce = function( array $tree ) use ( &$reduce )
        {
            $values = array();
            foreach ( $tree as $nr => $value )
            {
                if ( is_array( $value ) )
                {
                    $values[$nr] = array_sum( $reduce( $value ) );
                }
                else
                {
                    $values[$nr] = $value;
                }
            }

            return $values;
        };
        $values   = $reduce( $tree );
        $valueSum = array_sum( $values );
        $offset   = 0;

        foreach ( $values as $nr => $value )
        {
            $percent = $value / $valueSum;

            $padding = $this->padding->__invoke( $percent, $value );
            $rect = $root->ownerDocument->createElement( 'rect' );
            $rect->setAttribute( 'x', $cellX = $x + ( !$vertical ? $offset * $width : 0 ) );
            $rect->setAttribute( 'y', $cellY = $y + ( $vertical ? $offset * $height : 0 ) );
            $rect->setAttribute( 'width', $cellWidth = $vertical ? $width : $percent * $width );
            $rect->setAttribute( 'height', $cellHeight = !$vertical ? $height : $percent * $height );
            $rect->setAttribute( 'style', sprintf( 'fill: %s; fill-opacity: 1; %s',
                $this->cellColor->__invoke( $percent, $value ),
                $this->border->__invoke( $percent, $value )
            ) );
            
            $root->appendChild( $rect );

            if ( is_array( $tree[$nr] ) )
            {
                $this->renderSubtree( $root, $tree[$nr], $cellX + $padding, $cellY + $padding, $cellWidth - $padding * 2, $cellHeight - $padding * 2, $vertical ^ true );
            }
            else
            {
                $text = $root->ownerDocument->createElement( 'text', htmlspecialchars( $nr ) );
                $text->setAttribute( 'x', $cellX + $padding );
                $text->setAttribute( 'y', $cellY + $padding );
                $text->setAttribute( 'transform', 'rotate( ' . ( $vertical ? 90 : 0 ) . ", $cellX, $cellY )" );
                $text->setAttribute( 'style', $this->textProperties->__invoke( $percent, $tree[$nr], $nr ) );
                $root->appendChild( $text );
            }

            $offset += $percent;

        }
    }

    /**
     * Render treemap
     * 
     * @param array $tree 
     * @param float $width 
     * @param float $height 
     * @return DOMDocument
     */
    public function render( array $tree, $width, $height )
    {
        $document = new DOMDocument( '1.0' );
        $document->formatOutput = true;

        $svg = $document->createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
        $svg = $document->appendChild( $svg );

        $svg->setAttribute( 'width', $width );
        $svg->setAttribute( 'height', $height );
        $svg->setAttribute( 'version', '1.0' );

        $map = $document->createElement( 'g' );
        $map = $svg->appendChild( $map );

        $this->renderSubtree( $map, $tree, 0, 0, $width, $height );

        return $document;
    }
}


