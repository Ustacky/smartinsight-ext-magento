<?php

namespace SmartInsight\SmartInsightAI\Api;
/**
     * Interface for accepting strings and returning JSON
     */
interface SetupInterface
{
    /**
     * Return processed data as JSON.
     *
     * param string $input
     * return \Magento\Framework\DataObject
     * @return array
     */

    public function moduleSetup();
}