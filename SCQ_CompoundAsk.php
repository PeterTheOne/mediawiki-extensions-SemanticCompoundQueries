<?php

use SMW\MediaWiki\Api\Query;
use SMW\MediaWiki\Api\ApiRequestParameterFormatter;

/**
 * API module to query SMW by providing multiple queries in the ask language.
 *
 * @ingroup SemanticCompoundQueries
 *
 * @author Peter Grassberger < petertheone@gmail.com >
 */
class SCQCompoundAsk extends Query {

    /**
     * @see ApiBase::execute
     */
    public function execute() {
        $parameterFormatter = new ApiRequestParameterFormatter( $this->extractRequestParams() );
        $outputFormat = 'json';

        $parameters = $parameterFormatter->getAskApiParameters();

        /**
         * from SCQQueryProcessor::doCompoundQuery
         */
        $other_params = array();
        $results = array();
        $printRequests = array();
        $queryParams = array();

        foreach ( $parameters as $param ) {
            // Very primitive heuristic - if the parameter
            // includes a square bracket, then it's a
            // sub-query; otherwise it's a regular parameter.
            if ( strpos( $param, '[' ) !== false ) {
                $queryParams[] = $param;
            } else {
                $parts = explode( '=', $param, 2 );

                if ( count( $parts ) >= 2 ) {
                    $other_params[strtolower( trim( $parts[0] ) )] = $parts[1]; // don't trim here, some params care for " "
                }
            }
        }

        foreach ( $queryParams as $param ) {
            $subQueryParams = SCQQueryProcessor::getSubParams( $param );

            if ( array_key_exists( 'format', $other_params ) && !array_key_exists( 'format', $subQueryParams ) ) {
                $subQueryParams['format'] = $other_params['format'];
            }

            $next_result = SCQQueryProcessor::getQueryResultFromFunctionParams(
                $subQueryParams,
                SMW_OUTPUT_WIKI
            );

            $results = SCQQueryProcessor::mergeSMWQueryResults( $results, $next_result->getResults() );
            $printRequests = SCQQueryProcessor::mergeSMWPrintRequests( $printRequests, $next_result->getPrintRequests() );
        }

        // Sort results so that they'll show up by page name
        uasort( $results, array( 'SCQQueryProcessor', 'compareQueryResults' ) );

        $query_result = new SCQQueryResult( $printRequests, new SMWQuery(), $results, smwfGetStore() );


        if ( version_compare( SMW_VERSION, '1.6.1', '>' ) ) {
            SMWQueryProcessor::addThisPrintout( $printRequests, $other_params );
            $other_params = SMWQueryProcessor::getProcessedParams( $other_params, $printRequests );
        }

        if ( $this->getMain()->getPrinter() instanceof \ApiFormatXml ) {
            $outputFormat = 'xml';
        }

        $this->addQueryResult( $query_result, $outputFormat );
    }

    /**
     * @codeCoverageIgnore
     * @see ApiBase::getAllowedParams
     *
     * @return array
     */
    public function getAllowedParams() {
        return array(
            'query' => array(
                ApiBase::PARAM_TYPE => 'string',
                ApiBase::PARAM_REQUIRED => true,
            ),
        );
    }

    /**
     * @codeCoverageIgnore
     * @see ApiBase::getParamDescription
     *
     * @return array
     */
    public function getParamDescription() {
        return array(
            'query' => 'The multiple queries string in ask-language'
        );
    }

    /**
     * @codeCoverageIgnore
     * @see ApiBase::getDescription
     *
     * @return array
     */
    public function getDescription() {
        return array(
            'API module to query SMW by providing a multiple queries in the ask language.'
        );
    }

    /**
     * @codeCoverageIgnore
     * @see ApiBase::getExamples
     *
     * @return array
     */
    protected function getExamples() {
        return array(
            'api.php?action=compoundask&query=[[Category:Wien]]; ?Has coordinates|[[Category:Graz]]; ?Has coordinates',
            'api.php?action=compoundask&query=|[[Category:Wien]]; ?Has coordinates|[[Category:Graz]]; ?Has coordinates',
        );
    }

    /**
     * @codeCoverageIgnore
     * @see ApiBase::getVersion
     *
     * @return string
     */
    public function getVersion() {
        return __CLASS__ . '-' . SCQ_VERSION;
    }

}
