<?php
namespace SmartInsight\ReportAI\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Report implements \SmartInsight\ReportAI\Api\ReportInterface
{
    protected $dbConnection;
    protected $request;
    protected $scopeConfig;

    public function __construct(
        ResourceConnection $dbConnection,
        Http $request,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->dbConnection = $dbConnection;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
    }
    /**
     * {@inheritdoc}
     */
    public function processInput()
    {
        $rapidInsightHeader = $this->request->getHeader('RapidInsight-Token');

        if (!$rapidInsightHeader) {
            $errorMessage = 'Missing token: RapidInsight-Token';
            throw new Exception(__($errorMessage), 400);
        }

        // TODO
        // verify token
        // $rapidInsightHeaderValue = $rapidInsightHeader->getFieldValue();

        $jsonData = $this->request->getContent();
        // Decode JSON data to associative array
        $requestData = json_decode($jsonData, true);
        // Retrieve the 'sql_query' parameter from the decoded data
        $sql_query = isset($requestData['sql_query']) ? $requestData['sql_query'] : null;

        $commandList = [
            'insert',
            'update',
            'delete',
            'drop',
            'grant',
            'revoke',
            'describe',
            'truncate',
            'rollback',
            'commit',
            'savepoint',
        ];

        $sql_query_check = strtolower($sql_query);
        // $sql_query_check = preg_replace('/)(,\./', ' ', $sql_query_check);
        $splitQuery = explode(' ', $sql_query_check);
        // $splitQuery = array_map(function ($str) {return strtolower($str); }, $splitQuery);
        
        if (
            !$sql_query 
            || !str_contains(strtolower($sql_query), 'select')
            || count(array_intersect($commandList, $splitQuery)) > 0
        ) {
            // TODO trigger alert
            // return [
            //     ['sql_query' =>  $sql_query, 'commn' => $commandList, 'split' => $splitQuery,
            //     'contains' => !str_contains(strtolower($sql_query), 'select'),
            //     'inter' => count(array_intersect($commandList, $splitQuery)) > 0,
            //     ]
            // ];
            $errorMessage = 'Invalid operation';
            throw new Exception(__($errorMessage), 400);
        }

        $connection = $this->dbConnection->getConnection();
        $tablePrefix = $this->getDatabaseTablePrefix();

        if (!$tablePrefix) {
            $revisedQuery = $sql_query;
        } else {
            $revisedQuery = $this->prependTablePrefixToQuery($sql_query, $this->getDatabaseTablePrefix());
        }
        
        $result = $connection->fetchAll($revisedQuery); // Execute the SQL query and fetch all results

        $data = [
            'message' => 'Data returned',
            'data' => $result
        ];

        // var_dump($data);

        return [$data];

        // Convert the result to JSON format
        // $response = json_encode($result);
        // return $response;
    }

    protected function getDatabaseTablePrefix()
    {
        return $this->dbConnection->getTablePrefix() ?? '';
    }

    protected function getUnPrefixedTablenames()
    {
        $tablePrefix = $this->getDatabaseTablePrefix();

        return array_map(function ($tableName) use ($tablePrefix) {
            if (strpos($tableName, $tablePrefix ?? '') === 0) {
                return substr($tableName, 5);
            }
            return $tableName;
        }, $this->dbConnection->getConnection()->getTables());
    }

    protected function prependTablePrefixToQuery($query, $tablePrefix)
    {
        // return $query;
        $tables = $this->getUnPrefixedTablenames();

        // Regular expression pattern to match table names (words followed by a dot)
        $pattern = '/\b([a-zA-Z_][a-zA-Z0-9_]*)\b(?=[\s\.,]|$)/';

        // Replace all occurrences of table names in the query string with their prefixed versions        
        $prefixedQuery = preg_replace_callback(
            $pattern,
            function ($matches) use ($tables, $tablePrefix) {
                $tableName = $matches[1];
                // If the table name exists in the list of tables, prepend the prefix
                if (in_array($tableName, $tables)) {
                    return $tablePrefix . $tableName;
                }
                // If the table name doesn't exist in the list of tables, leave it unchanged
                return $matches[0];
            },
            $query
        );

        return $prefixedQuery;
    }

}
