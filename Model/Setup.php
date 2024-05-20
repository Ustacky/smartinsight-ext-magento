<?php
namespace SmartInsight\ReportAI\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Setup implements \SmartInsight\ReportAI\Api\SetupInterface
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

    public function moduleSetup()
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

        $data = [
            'sales_order_status' => [],
            'payment_methods' => [],
        ];

        try {

            $data['sales_order_status'] = $this->runSQLForSalesOrderStatus();
            $data['payment_methods'] = $this->runSQLForPaymentMethods() ?? [];

        } catch (\Exception $e) {
            throw new Exception(__($e->getMessage()), 555999);
        }

        return [$data];
    }

    protected function getDatabaseTablePrefix()
    {
        return $this->dbConnection->getTablePrefix() ?? '';
    }

    protected function getSQLForSalesOrderStatus()
    {
        $tablePrefix = $this->getDatabaseTablePrefix();
        $tableName = "sales_order";

        if ($tablePrefix) {
            $tableName = $tablePrefix . $tableName;
        }

        $query = "SELECT DISTINCT status from {$tableName} WHERE status IS NOT NULL AND status != ''";
        return $query;
    }

    protected function runSQLForSalesOrderStatus()
    {
        $sql = $this->getSQLForSalesOrderStatus();

        $connection = $this->dbConnection->getConnection();
        $salesOrderStatus = $connection->fetchAll($sql);

        $result = array_map(function ($salesOrder) {
            return $salesOrder['status'];
        }, $salesOrderStatus);

        return $result;
    }

    protected function getSQLForPaymentMethods()
    {
        $tablePrefix = $this->getDatabaseTablePrefix();
        $tableName = "sales_order_payment";

        if ($tablePrefix) {
            $tableName = $tablePrefix . $tableName;
        }

        $query = "SELECT DISTINCT method FROM {$tableName} WHERE method IS NOT NULL AND method != ''";
        return $query;
    }

    protected function runSQLForPaymentMethods()
    {
        $sql = $this->getSQLForPaymentMethods();

        $connection = $this->dbConnection->getConnection();
        $paymentMethods = $connection->fetchAll($sql);

        $result = array_map(function ($salesOrder) {
            return $salesOrder['method'];
        }, $paymentMethods);

        return $result;
    }
}
