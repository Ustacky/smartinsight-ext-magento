<?php
namespace SmartInsight\ReportAI\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;


class Report implements \SmartInsight\ReportAI\Api\ReportInterface
{
    protected $dbConnection;
    protected $request;
    protected $scopeConfig;
    protected $encryptor;

    public function __construct(
        ResourceConnection $dbConnection,
        Http $request,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->dbConnection = $dbConnection;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }
    /**
     * {@inheritdoc}
     */
    public function processInput()
    {
        $isEnabled = $this->scopeConfig->getValue('smartinsight/reportai/enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        
        if (!$isEnabled) {
            throw new Exception(__('The module is disabled'), 555001);
        }
        
        $authHeader = $this->request->getHeader('X-SmartInsight-ReportAI-Api-Key');
        
        if (!$authHeader) {
            $errorMessage = 'Missing API_KEY: X-SmartInsight-ReportAI-Api-Key';
            throw new Exception(__($errorMessage), 555101);
        }
        
        $encryptedApiKey = $this->scopeConfig->getValue('smartinsight/reportai/api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $apiKey = $this->encryptor->decrypt($encryptedApiKey);
        
        if ($authHeader !== $apiKey) {
            $errorMessage = 'Invalid API_KEY: X-SmartInsight-ReportAI-Api-Key';
            throw new Exception(__($errorMessage), 555101);
        }

        $jsonData = $this->request->getContent();
        // Decode JSON data to associative array
        $requestData = json_decode($jsonData, true);
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

        $sql_query = strtolower($sql_query);
        $splitQuery = explode(' ', $sql_query);
        // $sql_query = preg_replace('/)(,\./', ' ', $sql_query);
        // $splitQuery = array_map(function ($str) {return strtolower($str); }, $splitQuery);

        if (
            !$sql_query
            || !str_contains(strtolower($sql_query), 'select')
            || count(array_intersect($commandList, $splitQuery)) > 0
        ) {
            $errorMessage = 'Invalid operation '. $sql_query;
            throw new Exception(__($errorMessage), 555201);
        }

        try {

            $connection = $this->dbConnection->getConnection();
            $tablePrefix = $this->getDatabaseTablePrefix();

            if (!$tablePrefix) {
                $revisedQuery = $sql_query;
            } else {
                $revisedQuery = $this->prependTablePrefixToQuery($sql_query, $this->getDatabaseTablePrefix());
            }

            $result = $connection->fetchAll($revisedQuery); // Execute the SQL query and fetch all results

        } catch (\Exception $e) {
            throw new Exception(__($e->getMessage()), 555999);
        }

        $data = [
            'message' => 'Data returned',
            'data' => $result
        ];

        return [$data];
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
