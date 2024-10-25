<?php

/*********************************************************************************
 *
 * CONFIDENTIAL
 * __________________
 *
 *  Copyright (C) WrightWay Digital, Ltd.
 *  All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of WrightWay Digital Ltd and its suppliers, if any.
 * The intellectual and technical concepts contained herein are proprietary
 * to WrightWay Digital Ltd and its suppliers and may be covered by UK and Foreign Patents,
 * or patents in process, and are protected by trade secret or copyright law.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from WrightWay Digital Ltd.
 *
 * @author WrightWay Digital, Ltd.
 * @copyright 2023 WrightWay Digital, Ltd.
 * @license LICENSE.txt
 ********************************************************************************/

namespace Wrightwaydigital\Enconnector\Service\EposNow;
abstract class WebhookEnum
{
    const CreateBrand = 41;
    const UpdateBrand = 42;
    const DeleteBrand = 43;
    const CreateColour = 61;
    const UpdateColour = 62;
    const DeleteColour = 63;
    const CreateCustomer = 81;
    const UpdateCustomer = 82;
    const DeleteCustomer = 83;
    const CreateCustomerAddress = 84;
    const UpdateCustomerAddress = 85;
    const DeleteCustomerAddress = 86;
    const CreateCustomerType = 87;
    const UpdateCustomerType = 88;
    const DeleteCustomerType = 89;
    const CreateCustomerRating = 100;
    const UpdateCustomerRating = 101;
    const DeleteCustomerRating = 102;
    const CreateProduct = 121;
    const UpdateProduct = 122;
    const DeleteProduct = 123;
    const CreatePopupNote = 124;
    const UpdatePopupNote = 125;
    const DeletePopupNote = 126;
    const CreateProductDetail = 130;
    const UpdateProductDetail = 131;
    const DeleteProductDetail = 132;
    const CreateProductStockDetail = 201;
    const UpdateProductStockDetail = 202;
    const DeleteProductStockDetail = 203;
    const CreateSupplier = 204;
    const UpdateSupplier = 205;
    const DeleteSupplier = 206;
    const UpdateOutOfStockProduct = 231;
    const UpdateInStockProduct = 232;
    const CreateStockTransferReason = 240;
    const UpdateStockTransferReason = 241;
    const DeleteStockTransferReason = 242;
    const CreateTaxRate = 261;
    const UpdateTaxRate = 262;
    const DeleteTaxRate = 263;
    const CreateTaxGroup = 271;
    const UpdateTaxGroup = 272;
    const DeleteTaxGroup = 273;
    const CreateEndOfDay = 267;
    const CompleteTransaction = 304;
    const CreateOrderedTransaction = 305;
    const CancelOrderedTransaction = 308;
    const CreatePreparationStatus = 450;
    const UpdatePreparationStatus = 451;
    const DeletePreparationStatus = 452;
    const CreateCategory = 471;
    const UpdateCategory = 472;
    const DeleteCategory = 473;
    const CreateStaff = 501;
    const UpdateStaff = 502;
    const DeleteStaff = 503;
    const CreateClocking = 520;
    const UpdateClocking = 521;
    const DeleteClocking = 522;
    const BatchUpdate = 00;
}
