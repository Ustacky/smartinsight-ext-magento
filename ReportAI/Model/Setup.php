<?php
namespace SmartInsight\ReportAI\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Setup implements \SmartInsight\ReportAI\Api\SetupInterface
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

    public function moduleSetup()
    {
        $rapidInsightHeader = $this->request->getHeader('RapidInsight-Token');

        if (!$rapidInsightHeader) {
            $errorMessage = 'Missing token: RapidInsight-Token';
            throw new Exception(__($errorMessage), 400);
        }

        // TODO
        // verify token
        // $rapidInsightHeaderValue = $rapidInsightHeader->getFieldValue();
        
        $data = [
            'sales_order_status' => [],
            'payment_methods' => [],
        ];

        try {

            $data['sales_order_status'] = $this->runSQLForSalesOrderStatus();
            $data['payment_methods'] = $this->runSQLForPaymentMethods() ?? [];

        } catch (\Exception $e) {
            throw new Exception(__($e->getMessage()), 400);  
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
