<?php

namespace SmartInsight\ReportAI\Api;
/**
     * Interface for accepting strings and returning JSON
     */
interface ReportInterface
{
    /**
     * Return processed data as JSON.
     *
     * param string $input
     * return \Magento\Framework\DataObject
     * @return array
     */
    public function processInput();

}