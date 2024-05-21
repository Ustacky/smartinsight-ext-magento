<?php
namespace SmartInsight\SmartInsightAI\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Setup implements \SmartInsight\SmartInsightAI\Api\SetupInterface
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
        $isEnabled = $this->scopeConfig->getValue('smartinsight/smartinsightai/enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if (!$isEnabled) {
            throw new Exception(__('The module is disabled'), 555001);
        }

        $authHeader = $this->request->getHeader('X-SmartInsightAI-Api-Key');

        if (!$authHeader) {
            $errorMessage = 'Missing API_KEY: X-SmartInsightAI-Api-Key';
            throw new Exception(__($errorMessage), 555101);
        }

        $encryptedApiKey = $this->scopeConfig->getValue('smartinsight/smartinsightai/api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $apiKey = $this->encryptor->decrypt($encryptedApiKey);

        if ($authHeader !== $apiKey) {
            $errorMessage = 'Invalid API_KEY: X-SmartInsightAI-Api-Key';
            throw new Exception(__($errorMessage), 555101);
        }

        $data = [
            'sales_order_status' => [],
            'payment_methods' => [],
        ];

        try {
            $data['sales_order_status'] = $this->getSalesOrderStatus() ?? [];
            $data['payment_methods'] = $this->getPaymentMethods() ?? [];

        } catch (\Exception $e) {
            throw new Exception(__($e->getMessage()), 555999);
        }

        return [$data];
    }

    protected function getDatabaseTablePrefix()
    {
        return $this->dbConnection->getTablePrefix() ?? '';
    }

    protected function getSalesOrderStatus()
    {
        $tablePrefix = $this->getDatabaseTablePrefix();
        $tableName = "sales_order";

        if ($tablePrefix) {
            $tableName = $tablePrefix . $tableName;
        }

        $connection = $this->dbConnection->getConnection();

        $select = $connection->select()
            ->distinct(true)
            ->from($tableName, ['status'])
            ->where('status IS NOT NULL')
            ->where('status != ?', '');

        $results = $connection->fetchCol($select);
        return $results;
    }

    protected function getPaymentMethods()
    {
        $tablePrefix = $this->getDatabaseTablePrefix();
        $tableName = "sales_order_payment";

        if ($tablePrefix) {
            $tableName = $tablePrefix . $tableName;
        }

        $connection = $this->dbConnection->getConnection();

        $select = $connection->select()
            ->distinct(true)
            ->from($tableName, ['method'])
            ->where('method IS NOT NULL')
            ->where('method != ?', '');

        $results = $connection->fetchCol($select);
        return $results;
    }
}
