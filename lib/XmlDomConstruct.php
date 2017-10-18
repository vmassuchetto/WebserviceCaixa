<?
/**
 * EXTENDS THE DOMDOCUMENT TO IMPLEMENT PERSONAL (UTILITY) METHODS.
 *
 * @AUTHOR TONI VAN DE VOORDE
 */
class XmlDomConstruct extends DOMDocument {

    /**
     * CONSTRUCTS ELEMENTS AND TEXTS FROM AN ARRAY OR STRING.
     * THE ARRAY CAN CONTAIN AN ELEMENT'S NAME IN THE INDEX PART
     * AND AN ELEMENT'S TEXT IN THE VALUE PART.
     *
     * IT CAN ALSO CREATES AN XML WITH THE SAME ELEMENT TAGNAME ON THE SAME
     * LEVEL.
     *
     * EX:
     * <NODES>
     *   <NODE>TEXT</NODE>
     *   <NODE>
     *     <FIELD>HELLO</FIELD>
     *     <FIELD>WORLD</FIELD>
     *   </NODE>
     * </NODES>
     *
     * ARRAY SHOULD THEN LOOK LIKE:
     *
     * ARRAY (
     *   "NODES" => ARRAY (
     *     "NODE" => ARRAY (
     *       0 => "TEXT"
     *       1 => ARRAY (
     *         "FIELD" => ARRAY (
     *           0 => "HELLO"
     *           1 => "WORLD"
     *         )
     *       )
     *     )
     *   )
     * )
     *
     * @PARAM MIXED $MIXED AN ARRAY OR STRING.
     *
     * @PARAM DOMELEMENT[OPTIONAL] $DOMELEMENT THEN ELEMENT
     * FROM WHERE THE ARRAY WILL BE CONSTRUCT TO.
     *
     */
    public function fromMixed($mixed, DOMElement $domElement = null) {

        $domElement = is_null($domElement) ? $this : $domElement;

        if (is_array($mixed)) {
            foreach( $mixed as $index => $mixedElement ) {

                if ( is_int($index) ) {
                    if ( $index == 0 ) {
                        $node = $domElement;
                    } else {
                        $node = $this->createElement($domElement->tagName);
                        $domElement->parentNode->appendChild($node);
                    }
                }

                else {
					$node = $this->createElement($index);
                    $domElement->appendChild($node);
                }

                $this->fromMixed($mixedElement, $node);

            }
        } else {
            $domElement->appendChild($this->createTextNode($mixed));
        }

    }

}