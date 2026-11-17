<template>
  <div class="page">
    <PageHeader
      :title="isEdit ? $t('UpdateProduct') : $t('AddProduct')"
      :breadcrumb="[$t('Products'), isEdit ? $t('UpdateProduct') : $t('AddProduct')]"
    >
      <template #extra>
        <a-space>
          <a-button @click="$router.push('/products')">{{ $t('Cancel') }}</a-button>
          <a-button type="primary" :loading="submitting" @click="submit">{{ $t('submit') }}</a-button>
        </a-space>
      </template>
    </PageHeader>

    <div v-if="loadingRecord" style="display: flex; justify-content: center; padding: 96px 0">
      <a-spin size="large" />
    </div>

    <a-form v-else ref="formRef" :model="product" :rules="rules" layout="vertical">
      <a-row :gutter="[16, 16]">
        <!-- ================= LEFT COLUMN ================= -->
        <a-col :xs="24" :xl="16">
          <!-- Basic information -->
          <a-card size="small" :title="$t('BasicInformation')" style="margin-bottom: 16px">
            <a-row :gutter="16">
              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('type')" name="type">
                  <a-select
                    v-model:value="product.type" :disabled="isEdit"
                    :options="[
                      { value: 'is_single', label: $t('StandardProduct') },
                      { value: 'is_variant', label: $t('VariableProduct') },
                      { value: 'is_service', label: $t('ServiceProduct') },
                      { value: 'is_combo', label: $t('ComboProduct') },
                    ]"
                    @change="onTypeChange"
                  />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('Name_product')" name="name">
                  <a-input v-model:value="product.name" />
                </a-form-item>
              </a-col>

              <a-col :xs="24" :md="12">
                <a-form-item
                  :label="$t('CodeProduct')" name="code"
                  :validate-status="codeExists ? 'error' : ''" :help="codeExists || undefined"
                >
                  <div style="display: flex; gap: 8px">
                    <a-input v-model:value="product.code" style="flex: 1" @change="codeExists = ''">
                      <template #addonAfter>
                        <a @click="generateCode">{{ $t('Generate') }}</a>
                      </template>
                    </a-input>
                    <ProductScanModal @scan="onCodeScan" />
                  </div>
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('BarcodeSymbology')" name="Type_barcode">
                  <a-select
                    v-model:value="product.Type_barcode"
                    :options="['CODE128', 'CODE39', 'EAN13', 'EAN8', 'UPC'].map(v => ({ value: v, label: v }))"
                    @change="onSymbologyChange"
                  />
                </a-form-item>
              </a-col>
              <a-col v-if="showGtin" :xs="24" :md="8">
                <a-form-item :label="$t('Barcode_GTIN')">
                  <a-input v-model:value="product.gtin" />
                </a-form-item>
              </a-col>

              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('Categorie')" name="assigned_category_ids">
                  <a-select
                    v-model:value="product.assigned_category_ids" mode="multiple"
                    show-search option-filter-prop="label" :placeholder="$t('Choose_Category')"
                    :options="categoryOptions" @change="onCategoriesChange"
                  />
                  <a class="quick-add" @click="openQuickCategory">+ {{ $t('Add') }} {{ $t('Categorie') }}</a>
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('SubCategory')">
                  <a-select
                    v-model:value="product.assigned_subcategory_ids" mode="multiple" allow-clear
                    show-search option-filter-prop="label" :placeholder="$t('Choose_Sub_Category')"
                    :options="subcategoryOptions"
                  />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('Brand')">
                  <a-select
                    v-model:value="product.brand_id" allow-clear show-search option-filter-prop="label"
                    :placeholder="$t('Choose_Brand')" :options="brandOptions"
                  />
                  <a class="quick-add" @click="openQuickBrand">+ {{ $t('Add') }} {{ $t('Brand') }}</a>
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('Size_Guide')">
                  <a-select
                    v-model:value="product.size_guide_id" allow-clear show-search option-filter-prop="label"
                    :options="sizeGuides.map(s => ({ value: s.id, label: s.name }))"
                  />
                </a-form-item>
              </a-col>
            </a-row>
          </a-card>

          <!-- Pricing -->
          <a-card size="small" :title="$t('Price')" style="margin-bottom: 16px">
            <a-row :gutter="16">
              <a-col v-if="product.type !== 'is_variant'" :xs="12" :md="6">
                <a-form-item :label="$t('Cost')" name="cost">
                  <a-input-number v-model:value="product.cost" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col v-if="product.type !== 'is_variant'" :xs="12" :md="6">
                <a-form-item :label="$t('Price')" name="price">
                  <a-input-number v-model:value="product.price" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col v-if="hasTieredPricing" :xs="12" :md="6">
                <a-form-item :label="$t('Wholesale_Price')">
                  <a-input-number v-model:value="product.wholesale_price" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col v-if="hasTieredPricing" :xs="12" :md="6">
                <a-form-item :label="$t('Min_Selling_Price')">
                  <a-input-number v-model:value="product.min_price" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="6">
                <a-form-item :label="$t('OrderTax')">
                  <a-input-number v-model:value="product.TaxNet" :min="0" :max="100" addon-after="%" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="6">
                <a-form-item :label="$t('TaxMethod')" name="tax_method">
                  <a-select
                    v-model:value="product.tax_method"
                    :options="[{ value: '1', label: $t('Gen_Tax_Exclusive') }, { value: '2', label: $t('Gen_Tax_Inclusive') }]"
                  />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="6">
                <a-form-item :label="$t('Discount')">
                  <a-input-number v-model:value="product.discount" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="6">
                <a-form-item :label="$t('Discount_Method')" name="discount_method">
                  <a-select
                    v-model:value="product.discount_method"
                    :options="[{ value: '1', label: $t('Percentage') }, { value: '2', label: $t('Fixed') }]"
                  />
                </a-form-item>
              </a-col>
            </a-row>
          </a-card>

          <!-- Variable products: everything below the Price card moves to a second
               two-column row so the Variants card can span the full page width in
               between. Teleport keeps a single copy of these cards; when the product
               is not a variable product it is disabled and they render right here. -->
          <Teleport to="#pfLeftBottom" :disabled="!isVariantLayout" defer>
          <!-- Combo ingredients -->
          <a-card v-if="product.type === 'is_combo'" size="small" :title="$t('Products')" style="margin-bottom: 16px">
            <a-select
              :value="null" show-search :filter-option="false" :placeholder="$t('Search_product')"
              style="width: 100%; margin-bottom: 12px"
              :options="comboOptions" @search="onComboSearch" @select="addMateriel"
            />
            <a-table
              :columns="materielColumns" :data-source="materiels" :pagination="false"
              size="small" :row-key="r => r.product_id" :scroll="{ x: 'max-content' }"
              :locale="{ emptyText: $t('NodataAvailable') }"
            >
              <template #bodyCell="{ column, record, index }">
                <template v-if="column.key === 'quantity'">
                  <a-input-number v-model:value="record.quantity" :min="1" size="small" style="width: 100%" />
                </template>
                <template v-else-if="column.key === 'total'">
                  {{ (Number(record.cost || 0) * Number(record.quantity || 0)).toFixed(2) }}
                </template>
                <template v-else-if="column.key === 'action'">
                  <a-button size="small" danger @click="materiels.splice(index, 1)">
                    <template #icon><DeleteOutlined /></template>
                  </a-button>
                </template>
              </template>
              <template #footer>
                <div style="text-align: right">
                  <strong>{{ $t('Total') }}: {{ comboTotalCost.toFixed(2) }}</strong>
                </div>
              </template>
            </a-table>
          </a-card>

          <!-- Units -->
          <!-- Every type except service needs a base unit (backend: unit_id requiredIf type != is_service). -->
          <a-card v-if="product.type !== 'is_service'" size="small" :title="$t('Unit')" style="margin-bottom: 16px">
            <a-row :gutter="16">
              <a-col :xs="24" :md="8">
                <a-form-item :label="$t('BaseUnit')" name="unit_id">
                  <a-select
                    v-model:value="product.unit_id" show-search option-filter-prop="label"
                    :placeholder="$t('Choose_Base_Unit')" :options="unitOptions" @change="onBaseUnitChange"
                  />
                  <a class="quick-add" @click="openQuickUnit">+ {{ $t('Add') }} {{ $t('Unit') }}</a>
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="8">
                <a-form-item :label="$t('UnitSale')">
                  <a-select
                    v-model:value="product.unit_sale_id" allow-clear
                    :placeholder="$t('Choose_Unit_Sale')" :options="subUnitOptions"
                  />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="8">
                <a-form-item :label="$t('UnitPurchase')">
                  <a-select
                    v-model:value="product.unit_purchase_id" allow-clear
                    :placeholder="$t('Choose_Unit_Purchase')" :options="subUnitOptions"
                  />
                </a-form-item>
              </a-col>
            </a-row>
          </a-card>

          <!-- Inventory / dimensions -->
          <a-card size="small" :title="$t('Details')" style="margin-bottom: 16px">
            <a-row :gutter="16">
              <a-col :xs="12" :md="8">
                <a-form-item :label="$t('StockAlert')">
                  <a-input-number v-model:value="product.stock_alert" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="8">
                <a-form-item :label="$t('Points')">
                  <a-input-number v-model:value="product.points" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="8">
                <a-form-item :label="$t('Weight')">
                  <a-input-number v-model:value="product.weight" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col v-if="product.is_batch_tracked" :xs="12" :md="8">
                <a-form-item :label="$t('Shelf_Life_Days')">
                  <a-input-number v-model:value="product.shelf_life_days" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="8">
                <a-form-item :label="$t('Length')">
                  <a-input-number v-model:value="product.length" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="8">
                <a-form-item :label="$t('Width')">
                  <a-input-number v-model:value="product.width" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="8">
                <a-form-item :label="$t('Height')">
                  <a-input-number v-model:value="product.height" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
            </a-row>
          </a-card>

          <!-- Smart reordering (Purchases → Reorder suggestions). Blank = global default. -->
          <a-card
            v-if="product.type === 'is_single' || product.type === 'is_variant'"
            size="small" :title="tf('Reorder_Card', 'Reordering')" style="margin-bottom: 16px"
          >
            <p class="reorder-help">{{ tf('Reorder_Card_Help', 'Used by Reorder suggestions to work out when and how much to buy. Leave a field blank to use the default.') }}</p>
            <a-row :gutter="16">
              <a-col :xs="24" :md="12">
                <a-form-item :label="tf('Reorder_Preferred_Supplier', 'Preferred supplier')">
                  <a-select
                    v-model:value="reorder.preferred_provider_id" allow-clear show-search option-filter-prop="label"
                    :options="providers.map(p => ({ value: p.id, label: p.name }))"
                    :placeholder="tf('Reorder_Last_Supplier', 'The supplier it was last bought from')"
                  />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="6">
                <a-form-item :label="tf('Reorder_Lead_Time', 'Lead time (days)')">
                  <a-input-number v-model:value="reorder.lead_time_days" :min="0" :max="365" style="width: 100%" :placeholder="String(reorderDefaults.lead_time_days)" />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="6">
                <a-form-item :label="tf('Reorder_Safety_Days', 'Safety stock (days)')">
                  <a-input-number v-model:value="reorder.safety_days" :min="0" :max="365" style="width: 100%" :placeholder="String(reorderDefaults.safety_days)" />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="6">
                <a-form-item :label="tf('Reorder_Min_Qty', 'Minimum order')" :extra="tf('Reorder_In_Purchase_Unit', 'In the purchase unit')">
                  <a-input-number v-model:value="reorder.min_order_qty" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :xs="12" :md="6">
                <a-form-item :label="tf('Reorder_Multiple', 'Order in multiples of')" :extra="tf('Reorder_In_Purchase_Unit', 'In the purchase unit')">
                  <a-input-number v-model:value="reorder.order_multiple" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item :label="' '">
                  <a-checkbox v-model:checked="reorder.excluded">{{ tf('Reorder_Excluded', 'Never suggest reordering this product') }}</a-checkbox>
                </a-form-item>
              </a-col>
            </a-row>
          </a-card>

          <!-- Multi-pack selling (single products: one flat list; variant
               products: one pack list per variant) -->
          <a-card
            v-if="enableMultiPack && (product.type === 'is_single' || (product.type === 'is_variant' && variants.length))"
            size="small" :title="$t('Multi_Pack_Selling')" style="margin-bottom: 16px"
          >
            <template #extra>
              <a-button v-if="product.type === 'is_single'" size="small" @click="addPack">
                <template #icon><PlusOutlined /></template>
                {{ $t('Add') }}
              </a-button>
            </template>
            <a-table
              v-if="product.type === 'is_single'"
              :columns="packColumns" :data-source="packs" :pagination="false"
              size="small" :row-key="(_r, i) => i" :scroll="{ x: 'max-content' }"
            >
              <template #bodyCell="{ column, record, index }">
                <template v-if="column.key === 'name'">
                  <a-input v-model:value="record.name" size="small" :disabled="record.is_default" />
                </template>
                <template v-else-if="column.key === 'multiplier'">
                  <a-input-number v-model:value="record.multiplier" :min="1" size="small" :disabled="record.is_default" style="width: 100%" />
                </template>
                <template v-else-if="column.key === 'price'">
                  <a-input-number v-model:value="record.price" :min="0" size="small" style="width: 100%" />
                </template>
                <template v-else-if="column.key === 'is_active'">
                  <a-switch v-model:checked="record.is_active" size="small" :disabled="record.is_default" />
                </template>
                <template v-else-if="column.key === 'action'">
                  <a-button v-if="!record.is_default" size="small" danger @click="packs.splice(index, 1)">
                    <template #icon><DeleteOutlined /></template>
                  </a-button>
                  <a-tag v-else>{{ $t('Default') }}</a-tag>
                </template>
              </template>
            </a-table>
            <template v-else>
              <div v-for="v in variants" :key="v.var_id" class="variant-pack-block">
                <div class="variant-pack-head">
                  <strong>{{ v.text }}</strong>
                  <a-button size="small" @click="addVariantPack(v)">
                    <template #icon><PlusOutlined /></template>
                    {{ $t('Add') }}
                  </a-button>
                </div>
                <a-table
                  :columns="packColumns" :data-source="v.packs || []" :pagination="false"
                  size="small" :row-key="(_r, i) => i" :scroll="{ x: 'max-content' }"
                >
                  <template #bodyCell="{ column, record, index }">
                    <template v-if="column.key === 'name'">
                      <a-input v-model:value="record.name" size="small" :disabled="record.is_default" />
                    </template>
                    <template v-else-if="column.key === 'multiplier'">
                      <a-input-number v-model:value="record.multiplier" :min="1" size="small" :disabled="record.is_default" style="width: 100%" />
                    </template>
                    <template v-else-if="column.key === 'price'">
                      <a-input-number v-model:value="record.price" :min="0" size="small" style="width: 100%" />
                    </template>
                    <template v-else-if="column.key === 'is_active'">
                      <a-switch v-model:checked="record.is_active" size="small" :disabled="record.is_default" />
                    </template>
                    <template v-else-if="column.key === 'action'">
                      <a-button v-if="!record.is_default" size="small" danger @click="v.packs.splice(index, 1)">
                        <template #icon><DeleteOutlined /></template>
                      </a-button>
                      <a-tag v-else>{{ $t('Default') }}</a-tag>
                    </template>
                  </template>
                </a-table>
              </div>
            </template>
          </a-card>

          <!-- Wholesale pricing by quantity — quantity breaks that override the
               retail price once the ordered quantity reaches them (POS + store) -->
          <a-card
            v-if="enableWholesalePricing"
            size="small" :title="$t('Wholesale_Pricing_By_Quantity')" style="margin-bottom: 16px"
          >
            <template #extra>
              <a-button size="small" @click="addPriceTier">
                <template #icon><PlusOutlined /></template>
                {{ $t('Add') }}
              </a-button>
            </template>
            <a-alert
              type="info" show-icon
              :message="$t('Wholesale_Pricing_By_Quantity_Hint')"
              :description="product.type === 'is_variant' ? $t('Wholesale_Pricing_Variants_Hint') : undefined"
              style="margin-bottom: 12px"
            />
            <a-table
              :columns="priceTierColumns" :data-source="priceTiers" :pagination="false"
              size="small" :row-key="(_r, i) => i" :scroll="{ x: 'max-content' }"
            >
              <template #bodyCell="{ column, record, index }">
                <template v-if="column.key === 'min_qty'">
                  <a-input-number v-model:value="record.min_qty" :min="1" size="small" style="width: 100%" />
                </template>
                <template v-else-if="column.key === 'max_qty'">
                  <a-input-number
                    v-model:value="record.max_qty" :min="record.min_qty || 1" size="small"
                    style="width: 100%" :placeholder="$t('No_maximum')"
                  />
                </template>
                <template v-else-if="column.key === 'price'">
                  <a-input-number v-model:value="record.price" :min="0" size="small" style="width: 100%" />
                </template>
                <template v-else-if="column.key === 'action'">
                  <a-button size="small" danger @click="priceTiers.splice(index, 1)">
                    <template #icon><DeleteOutlined /></template>
                  </a-button>
                </template>
              </template>
            </a-table>
          </a-card>

          <!-- Opening stock -->
          <a-card
            v-if="(product.type === 'is_single' || (!isEdit && product.type === 'is_variant' && variants.length)) && warehouses.length"
            size="small" :title="$t('OpeningStock')" style="margin-bottom: 16px"
          >
            <div class="wh-grid">
              <div v-for="w in warehouses" :key="w.id" class="wh-tile">
                <div class="wh-head">
                  <span class="wh-icon"><ShopOutlined /></span>
                  <span class="wh-name" :title="w.name">{{ w.name }}</span>
                </div>
                <!-- Variant products (create only): one opening quantity per variant. -->
                <div v-if="product.type === 'is_variant'" class="wh-variants">
                  <div v-for="v in variants" :key="v.var_id" class="wh-variant-row">
                    <span class="wh-variant-name" :title="v.text">{{ v.text }}</span>
                    <a-input-number
                      :value="v.opening?.[w.id]" :min="0" :placeholder="$t('Quantity')" class="wh-qty"
                      @update:value="val => setVariantOpening(v, w.id, val)"
                    />
                  </div>
                </div>
                <div v-else class="wh-fields">
                  <a-input-number
                    v-if="!isEdit" v-model:value="warehouseRows[w.id].qte"
                    :min="0" :placeholder="$t('Quantity')" class="wh-qty"
                  />
                  <!-- Location assignment is an EDIT-only concern: on create the
                       tile shows just the warehouse + opening quantity. -->
                  <template v-if="isEdit">
                    <a-select
                      v-model:value="warehouseRows[w.id].warehouse_location_id"
                      allow-clear :placeholder="$t('Location')" class="wh-loc"
                      :options="(locationsByWarehouse[w.id] || []).map(l => ({ value: l.id, label: l.label }))"
                    />
                    <a-tooltip :title="$t('Add')">
                      <a-button @click="openQuickLocation(w.id)">
                        <template #icon><PlusOutlined /></template>
                      </a-button>
                    </a-tooltip>
                  </template>
                </div>
              </div>
            </div>
          </a-card>

          <!-- Storefront: tags / FAQs -->
          <a-card size="small" :title="$t('Store')" style="margin-bottom: 16px">
            <a-form-item
              :label="$t('Estimated_Delivery_Date')" :extra="$t('Estimated_Delivery_Date_Hint')"
            >
              <a-input v-model:value="product.preorder_available_date" type="date" allow-clear />
            </a-form-item>
            <a-form-item
              v-if="shippingMethods.length"
              :label="$t('Shipping_Methods')" :extra="$t('Product_Shipping_Methods_Hint')"
            >
              <a-select
                v-model:value="product.shipping_method_ids" mode="multiple" allow-clear
                :placeholder="$t('All_shipping_methods')"
                :options="shippingMethods.map(m => ({ label: m.name, value: Number(m.id) }))"
                show-search option-filter-prop="label"
              />
            </a-form-item>
            <a-form-item :label="$t('Related_Products')" :extra="$t('Related_Products_Hint')">
              <a-select
                v-model:value="relatedIds" mode="multiple" allow-clear
                :placeholder="$t('Search_product')" :filter-option="false"
                :options="relatedOptions" @search="onRelatedSearch"
              />
            </a-form-item>
            <a-form-item :label="$t('Labels')" :extra="$t('Product_Labels_Hint')">
              <a-select
                v-model:value="labels" mode="tags" :token-separators="[',']"
                :placeholder="$t('Add_a_label')" :options="labelSuggestions" :max-tag-count="5"
              />
            </a-form-item>
            <a-form-item :label="$t('Tags')">
              <a-select v-model:value="tags" mode="tags" :token-separators="[',']" :placeholder="$t('Tags')" />
            </a-form-item>
            <a-form-item :label="'FAQ'" style="margin-bottom: 0">
              <div v-for="(f, i) in faqs" :key="'faq' + i" class="faq-row">
                <a-input v-model:value="f.question" :placeholder="$t('Question')" style="flex: 1" />
                <a-input v-model:value="f.answer" :placeholder="$t('Answer')" style="flex: 2" />
                <a-button danger @click="faqs.splice(i, 1)">
                  <template #icon><DeleteOutlined /></template>
                </a-button>
              </div>
              <a-button size="small" style="margin-top: 8px" @click="faqs.push({ question: '', answer: '' })">
                <template #icon><PlusOutlined /></template>
                {{ $t('Add') }}
              </a-button>
            </a-form-item>
          </a-card>

          <!-- Shopify: pair with a listing that already exists in a shop -->
          <ShopifyLinkCard
            v-if="product.type !== 'is_combo'"
            ref="shopifyCardRef"
            :product-id="isEdit ? Number(id) : null"
            :variants="variants"
            :product-code="product.code || ''"
            :is-variant="product.type === 'is_variant'"
          />

          <!-- Pharmacy (only when batch/expiry tracking is enabled in the right column) -->
          <a-card v-if="product.is_batch_tracked" size="small" :title="$t('Set_Pharmacy')" style="margin-bottom: 16px">
            <a-alert type="info" show-icon :message="$t('Pharmacy_Batch_Expiry_Note')" style="margin-bottom: 16px" />
            <a-row :gutter="16">
              <a-col :xs="24" :md="8">
                <a-form-item :label="$t('Generic_Name')"><a-input v-model:value="product.generic_name" /></a-form-item>
              </a-col>
              <a-col :xs="12" :md="8">
                <a-form-item :label="$t('Strength')"><a-input v-model:value="product.strength" /></a-form-item>
              </a-col>
              <a-col :xs="12" :md="8">
                <a-form-item :label="$t('Dosage_Form')"><a-input v-model:value="product.dosage_form" /></a-form-item>
              </a-col>
              <a-col :xs="12" :md="8">
                <a-form-item :label="$t('Pack_Size')"><a-input v-model:value="product.pack_size" /></a-form-item>
              </a-col>
              <a-col :xs="12" :md="8">
                <a-form-item :label="$t('Manufacturer')"><a-input v-model:value="product.manufacturer" /></a-form-item>
              </a-col>
              <a-col :xs="12" :md="8">
                <a-form-item :label="$t('Drug_Schedule')"><a-input v-model:value="product.drug_schedule" /></a-form-item>
              </a-col>
              <a-col :span="24">
                <a-checkbox v-model:checked="product.prescription_required">{{ $t('Prescription_Required') }}</a-checkbox>
              </a-col>
            </a-row>
          </a-card>

          <a-card size="small" style="margin-bottom: 16px">
            <a-form-item style="margin-bottom: 0">
              <template #label>
                <span>{{ $t('Note') }}</span>
                <!-- AI product writer: drafts description / tags / FAQs into the form -->
                <a-button
                  v-if="aiWriterReady" size="small" type="link" class="ai-write-btn"
                  @click="aiWriterOpen = true"
                >
                  <template #icon><ThunderboltOutlined /></template>
                  {{ tf('Ai_Write_Product', 'Write with AI') }}
                </a-button>
              </template>
              <a-textarea v-model:value="product.note" :rows="3" :placeholder="$t('Afewwords')" />
            </a-form-item>
          </a-card>
          </Teleport>
        </a-col>

        <!-- ================= RIGHT COLUMN ================= -->
        <a-col :xs="24" :xl="8">
          <!-- Gallery (legacy has no separate main-image picker: the gallery
               image flagged ★ is the product's main image) -->
          <a-card size="small" :title="$t('ProductImagesGallery')" style="margin-bottom: 16px">
            <a-upload
              :file-list="[]" :before-upload="f => { addGalleryFile(f); return false; }"
              accept="image/*" multiple
            >
              <a-button><UploadOutlined /> {{ $t('Choose_files') }}</a-button>
            </a-upload>
            <!-- Or paste an image link (Unsplash, CDN, supplier catalog…) -->
            <div class="gallery-link-row">
              <a-input
                v-model:value="galleryUrlInput"
                :placeholder="$t('Paste_image_link')"
                allow-clear
                @pressEnter="addGalleryUrl"
              >
                <template #prefix><LinkOutlined /></template>
              </a-input>
              <a-button @click="addGalleryUrl" :disabled="!galleryUrlInput.trim()">{{ $t('Add_Link') }}</a-button>
            </div>
            <div class="gallery-link-hint">{{ $t('Image_link_hint') }}</div>
            <div v-if="gallery.length" class="gallery">
              <div v-for="(g, i) in gallery" :key="g._uid" class="gallery-tile" :class="{ main: g.is_main }">
                <img :src="g.url" @error="onGalleryImgError(g)" />
                <span v-if="g._url || g.remote" class="gallery-link-badge"><LinkOutlined /></span>
                <div class="gallery-actions">
                  <a-tooltip :title="$t('image')">
                    <a-button size="small" :type="g.is_main ? 'primary' : 'default'" @click="setMain(i)">★</a-button>
                  </a-tooltip>
                  <a-button size="small" :disabled="i === 0" @click="moveGallery(i, -1)">↑</a-button>
                  <a-button size="small" :disabled="i === gallery.length - 1" @click="moveGallery(i, 1)">↓</a-button>
                  <a-button size="small" danger @click="removeGallery(i)">×</a-button>
                </div>
              </div>
            </div>
          </a-card>

          <Teleport to="#pfRightBottom" :disabled="!isVariantLayout" defer>
          <!-- Flags -->
          <a-card size="small" :title="$t('Status')" style="margin-bottom: 16px">
            <a-space direction="vertical" size="small" style="width: 100%">
              <a-checkbox v-model:checked="product.is_active">{{ $t('Active') }}</a-checkbox>
              <a-checkbox v-model:checked="product.not_selling">{{ $t('This_Product_Not_For_Selling') }}</a-checkbox>
              <a-checkbox v-if="showSerialTracking && product.type === 'is_single'" v-model:checked="product.is_imei">
                {{ $t('Track_Serial_IMEI') }}
              </a-checkbox>
              <a-checkbox v-if="product.type === 'is_single'" v-model:checked="product.is_batch_tracked">
                {{ $t('Track_Batches_Expiry') }} ({{ $t('Pharmacy_Mode') }})
              </a-checkbox>
              <a-divider style="margin: 8px 0" />
              <a-checkbox v-model:checked="product.is_featured">{{ $t('Featured_Product') }}</a-checkbox>
              <a-checkbox v-model:checked="product.hide_from_online_store">{{ $t('Hide_From_Online_Store') }}</a-checkbox>
              <a-checkbox v-model:checked="product.is_returnable">{{ $t('Is_Returnable') }}</a-checkbox>
              <a-checkbox v-model:checked="product.is_classified">{{ $t('Sold_As_Classified') }}</a-checkbox>
            </a-space>
          </a-card>

          <!-- Pre-order -->
          <a-card size="small" :title="$t('PreOrder')" style="margin-bottom: 16px">
            <a-checkbox v-model:checked="product.is_preorder">{{ $t('Enable_Preorder') }}</a-checkbox>
            <template v-if="product.is_preorder">
              <a-checkbox v-model:checked="product.preorder_always" style="display: block; margin: 10px 0 0">
                {{ $t('Always_Preorder') }}
              </a-checkbox>
              <a-alert
                type="info" show-icon style="margin: 12px 0"
                :message="$t('Preorder_Uses_Delivery_Date')"
              />
              <a-form-item :label="$t('Preorder_Limit')">
                <a-input-number v-model:value="product.preorder_limit" :min="0" style="width: 100%" />
              </a-form-item>
              <a-form-item :label="$t('Preorder_Note')" style="margin-bottom: 0">
                <a-textarea v-model:value="product.preorder_note" :rows="2" />
              </a-form-item>
            </template>
          </a-card>

          <!-- Warranty / guarantee -->
          <a-card size="small" :title="$t('Warranty_Guarantee_Tracking')">
            <a-row :gutter="12">
              <a-col :span="12">
                <a-form-item :label="$t('Warranty_Period')">
                  <a-input-number v-model:value="product.warranty_period" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :span="12">
                <a-form-item label=" ">
                  <a-select v-model:value="product.warranty_unit" :options="periodUnits" />
                </a-form-item>
              </a-col>
              <a-col :span="24">
                <a-form-item :label="$t('WarrantyTerms')">
                  <a-input v-model:value="product.warranty_terms" :placeholder="$t('Enter_warranty_terms')" />
                </a-form-item>
              </a-col>
              <a-col :span="24">
                <a-checkbox v-model:checked="product.has_guarantee">{{ $t('HasGuarantee') }}</a-checkbox>
              </a-col>
              <template v-if="product.has_guarantee">
                <a-col :span="12" style="margin-top: 12px">
                  <a-form-item :label="$t('Guarantee_Period')">
                    <a-input-number v-model:value="product.guarantee_period" :min="0" style="width: 100%" />
                  </a-form-item>
                </a-col>
                <a-col :span="12" style="margin-top: 12px">
                  <a-form-item label=" ">
                    <a-select v-model:value="product.guarantee_unit" :options="periodUnits" />
                  </a-form-item>
                </a-col>
              </template>
            </a-row>
          </a-card>
          </Teleport>
        </a-col>
      </a-row>

      <!-- Variable products: full-width Variants card, then the remaining cards
           continue in the same two columns (targets of the Teleports above).
           v-show, not v-if: the Teleport targets must exist in the DOM before
           the product type is switched to "variable", or the move fails. -->
      <div v-show="isVariantLayout">
        <!-- Variants -->
        <a-card v-if="product.type === 'is_variant'" size="small" :title="$t('Variants')" style="margin-bottom: 16px">
          <template #extra>
            <a-space size="small" wrap>
              <a-radio-group :value="variantMode" size="small" button-style="solid" @update:value="switchVariantMode">
                <a-radio-button value="options">{{ $t('Variant_Options') }}</a-radio-button>
                <a-radio-button value="list">{{ $t('Simple_List') }}</a-radio-button>
              </a-radio-group>
              <a-button size="small" @click="variantGuideOpen = true">
                <template #icon><QuestionCircleOutlined /></template>
                {{ $t('Guide') }}
              </a-button>
            </a-space>
          </template>

          <!-- Options mode (Shopify-style): Color / Size / ... -> generated combinations -->
          <template v-if="variantMode === 'options'">
            <div class="variant-options-hint">{{ $t('Variants_Options_Hint') }}</div>
            <div v-for="(opt, oi) in options" :key="opt.key" class="variant-option-block">
              <div class="variant-option-row">
                <a-auto-complete
                  v-model:value="opt.name" :options="optionNameSuggestions" :placeholder="$t('Option_Name')"
                  class="variant-option-name"
                  :filter-option="(input, o) => String(o.value).toLowerCase().includes(String(input).toLowerCase())"
                />
                <a-select
                  :value="opt.values" mode="tags" :token-separators="[',']" :open="false"
                  :placeholder="$t('Option_Values_Placeholder')" class="variant-option-values"
                  @update:value="vals => onOptionValuesChange(opt, vals)"
                />
                <a-checkbox v-model:checked="opt.useSwatches" class="variant-option-swatch-toggle">{{ $t('Swatches') }}</a-checkbox>
                <a-button danger @click="removeOption(oi)">
                  <template #icon><DeleteOutlined /></template>
                </a-button>
              </div>
              <!-- Color swatches: one picker per value, shown on the storefront option buttons. -->
              <div v-if="opt.useSwatches && opt.values.length" class="variant-swatches">
                <label v-for="v in opt.values" :key="v" class="variant-swatch">
                  <input type="color" :value="opt.swatches[v] || '#cccccc'" @input="e => { opt.swatches[v] = e.target.value; }" />
                  <span>{{ v }}</span>
                </label>
              </div>
            </div>
            <div class="variant-options-actions">
              <a-button :disabled="options.length >= MAX_OPTIONS" @click="addOption">
                <template #icon><PlusOutlined /></template>
                {{ $t('Add_Option') }}
              </a-button>
              <a-button type="primary" :disabled="!canGenerate" @click="generateVariants">
                <template #icon><ReloadOutlined /></template>
                {{ $t('Generate_Variants') }}
              </a-button>
            </div>
          </template>

          <!-- List mode (legacy): free-text variant names -->
          <a-input-group v-else compact style="display: flex; margin-bottom: 12px">
            <a-input v-model:value="variantTag" :placeholder="$t('Enter_the_Variant')" @press-enter="addVariant" />
            <a-button type="primary" @click="addVariant">
              <template #icon><PlusOutlined /></template>
              {{ $t('Add') }}
            </a-button>
          </a-input-group>

          <!-- Bulk apply a cost / price to every row -->
          <div v-if="variants.length > 1" class="variant-bulk">
            <span class="variant-bulk-label">{{ $t('Apply_To_All') }}:</span>
            <a-input v-model:value="bulk.cost" size="small" :placeholder="$t('Cost')" class="variant-bulk-input" @press-enter="applyBulk('cost')" />
            <a-button size="small" :disabled="bulk.cost === ''" @click="applyBulk('cost')">{{ $t('Cost') }}</a-button>
            <a-input v-model:value="bulk.price" size="small" :placeholder="$t('Price')" class="variant-bulk-input" @press-enter="applyBulk('price')" />
            <a-button size="small" :disabled="bulk.price === ''" @click="applyBulk('price')">{{ $t('Price') }}</a-button>
          </div>

          <a-table
            :columns="variantColumns" :data-source="variants" :pagination="false"
            size="small" :row-key="r => r.var_id" :scroll="{ x: 'max-content' }"
            :locale="{ emptyText: $t('NodataAvailable') }"
          >
            <template #bodyCell="{ column, record, index }">
              <template v-if="column.key === 'image'">
                <a-upload
                  :file-list="[]" :before-upload="f => { pickVariantImage(record, f); return false; }"
                  accept="image/*" :max-count="1"
                >
                  <img
                    v-if="record.imagePreview || (record.image && record.image !== 'no-image.png')"
                    :src="record.imagePreview || (isRemoteImage(record.image) ? record.image : `/images/products/${record.image}`)"
                    class="variant-thumb"
                  />
                  <a-button v-else size="small"><UploadOutlined /></a-button>
                </a-upload>
              </template>
              <template v-else-if="column.key === 'code'">
                <div style="display: flex; gap: 4px; align-items: center">
                  <a-input v-model:value="record.code" size="small" style="flex: 1" />
                  <a-button size="small" type="text" :title="$t('Generate')" class="variant-code-btn" @click="generateVariantCode(record)">
                    <template #icon><Barcode :size="15" /></template>
                  </a-button>
                </div>
              </template>
              <template v-else-if="column.key === 'gtin'"><a-input v-model:value="record.gtin" size="small" /></template>
              <template v-else-if="column.key === 'text'">
                <template v-if="variantMode === 'options'">
                  <span>{{ record.text }}</span>
                  <a-tag v-if="!isVariantComplete(record)" color="error" style="margin-left: 6px">{{ $t('Incomplete') }}</a-tag>
                </template>
                <a-input v-else v-model:value="record.text" size="small" />
              </template>
              <template v-else-if="column.optionKey">
                <a-select
                  :value="record.option_values?.[column.optionKey]" size="small" style="min-width: 110px" allow-clear
                  :options="(optionByKey(column.optionKey)?.values || []).map(v => ({ value: v, label: v }))"
                  @update:value="val => setVariantOptionValue(record, column.optionKey, val)"
                />
              </template>
              <template v-else-if="column.key === 'cost'"><a-input v-model:value="record.cost" size="small" /></template>
              <template v-else-if="column.key === 'price'"><a-input v-model:value="record.price" size="small" /></template>
              <template v-else-if="column.key === 'wholesale'"><a-input v-model:value="record.wholesale" size="small" /></template>
              <template v-else-if="column.key === 'min_price'"><a-input v-model:value="record.min_price" size="small" /></template>
              <template v-else-if="column.key === 'action'">
                <a-button size="small" danger @click="variants.splice(index, 1)">
                  <template #icon><DeleteOutlined /></template>
                </a-button>
              </template>
            </template>
          </a-table>
        </a-card>

        <!-- Variants guide: right-side drawer explaining the whole workflow -->
        <a-drawer v-model:open="variantGuideOpen" :title="$t('Variants_Guide')" placement="right" width="min(560px, 100vw)">
          <p class="guide-intro">{{ $t('VG_Intro') }}</p>

          <div class="guide-section">
            <div class="guide-title"><AppstoreOutlined /> {{ $t('VG_Options_Title') }}</div>
            <ol class="guide-steps">
              <li v-for="n in 8" :key="n">{{ $t('VG_Opt_Step' + n) }}</li>
            </ol>
          </div>

          <a-divider class="guide-divider" />

          <div class="guide-section">
            <div class="guide-title"><TableOutlined /> {{ $t('VG_Rows_Title') }}</div>
            <p class="guide-intro">{{ $t('VG_Rows_Text') }}</p>
          </div>

          <a-divider class="guide-divider" />

          <div class="guide-section">
            <div class="guide-title"><UnorderedListOutlined /> {{ $t('VG_List_Title') }}</div>
            <p class="guide-intro">{{ $t('VG_List_Text') }}</p>
          </div>

          <a-divider class="guide-divider" />

          <div class="guide-section">
            <div class="guide-title"><EditOutlined /> {{ $t('VG_Edit_Title') }}</div>
            <ul class="guide-list">
              <li v-for="n in 4" :key="n">{{ $t('VG_Edit_' + n) }}</li>
            </ul>
          </div>

          <a-divider class="guide-divider" />

          <div class="guide-section">
            <div class="guide-title"><ShopOutlined /> {{ $t('VG_Store_Title') }}</div>
            <p class="guide-intro">{{ $t('VG_Store_Text') }}</p>
          </div>
        </a-drawer>

        <a-row :gutter="[16, 16]">
          <a-col :xs="24" :xl="16"><div id="pfLeftBottom"></div></a-col>
          <a-col :xs="24" :xl="8"><div id="pfRightBottom"></div></a-col>
        </a-row>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px">
        <a-button size="large" @click="$router.push('/products')">{{ $t('Cancel') }}</a-button>
        <a-button type="primary" size="large" :loading="submitting" @click="submit">{{ $t('submit') }}</a-button>
      </div>
    </a-form>

    <!-- ===== Quick-create modals ===== -->
    <a-modal v-model:open="quickCategoryOpen" :title="$t('Add_Category')" :confirm-loading="quickBusy" @ok="saveQuickCategory">
      <a-form layout="vertical" style="margin-top: 12px">
        <a-form-item :label="$t('Code')"><a-input v-model:value="quickCategory.code" /></a-form-item>
        <a-form-item :label="$t('Name')"><a-input v-model:value="quickCategory.name" /></a-form-item>
      </a-form>
    </a-modal>

    <a-modal v-model:open="quickBrandOpen" :title="$t('Prod_Add_Brand')" :confirm-loading="quickBusy" @ok="saveQuickBrand">
      <a-form layout="vertical" style="margin-top: 12px">
        <a-form-item :label="$t('Name')"><a-input v-model:value="quickBrand.name" /></a-form-item>
        <a-form-item :label="$t('Description')"><a-textarea v-model:value="quickBrand.description" :rows="2" /></a-form-item>
      </a-form>
    </a-modal>

    <!-- Same fields as the Units page modal (pages/Units.vue): name, short
         name, optional base unit and, when derived, operator + value. -->
    <a-modal v-model:open="quickUnitOpen" :title="$t('Prod_Add_Unit')" :confirm-loading="quickBusy" @ok="saveQuickUnit">
      <a-form layout="vertical" style="margin-top: 12px">
        <a-form-item :label="$t('Name')" required><a-input v-model:value="quickUnit.name" /></a-form-item>
        <a-form-item :label="$t('ShortName')" required><a-input v-model:value="quickUnit.ShortName" /></a-form-item>
        <a-form-item :label="$t('BaseUnit')">
          <a-select
            v-model:value="quickUnit.base_unit" allow-clear show-search option-filter-prop="label"
            :options="unitOptions" :placeholder="$t('BaseUnit')"
          />
        </a-form-item>
        <!-- Operator only applies when this unit derives from a base unit. -->
        <template v-if="quickUnit.base_unit">
          <a-form-item :label="$t('Operator')">
            <a-select
              v-model:value="quickUnit.operator"
              :options="[{ value: '*', label: '* (multiply)' }, { value: '/', label: '/ (divide)' }]"
            />
          </a-form-item>
          <a-form-item :label="$t('OperationValue')">
            <a-input-number v-model:value="quickUnit.operator_value" style="width: 100%" :min="0" />
          </a-form-item>
        </template>
      </a-form>
    </a-modal>

    <a-modal v-model:open="quickLocationOpen" :title="$t('Prod_Add_Location')" :confirm-loading="quickBusy" @ok="saveQuickLocation">
      <a-form layout="vertical" style="margin-top: 12px">
        <a-form-item :label="$t('Code')"><a-input v-model:value="quickLocation.code" /></a-form-item>
        <a-form-item :label="$t('Name')"><a-input v-model:value="quickLocation.name" /></a-form-item>
      </a-form>
    </a-modal>

    <AiProductWriterModal
      v-if="aiWriterReady"
      v-model:open="aiWriterOpen"
      :facts="aiFacts"
      :product-id="isEdit ? Number(id) : null"
      @apply="applyAiDraft"
    />
  </div>
</template>

<script setup>
/**
 * Product create/edit — FULL parity with legacy Add_product.vue (4,718 lines)
 * and Edit_product.vue (4,211 lines); only the presentation is new.
 *
 * Bootstrap: GET products/create → {categories, subcategories, brands,
 * size_guides, units, warehouses (id/name/qte), warehouse_locations,
 * show_product_gtin, show_serial_tracking, enable_multi_pack_selling,
 * enable_wholesale_pricing}.
 * Edit: GET products/{id}/edit → {product (+ProductVariant, packs, price_tiers, tags,
 * faqs, product_images, assigned_category_ids/assigned_subcategory_ids),
 * categories, all_subcategories, brands, size_guides, units, units_sub,
 * warehouses, warehouse_locations, product_warehouse_locations, materiels}.
 *
 * Save is multipart, and the two legacy payloads differ — both reproduced:
 *  - create POST products: variants as a JSON string, warehouses JSON
 *    {wid:{qte, warehouse_location_id}}, product_gallery_json {main_index}
 *  - edit POST products/{id} + _method=put: variants as bracket fields
 *    variants[i][key], warehouse_locations JSON {wid:{warehouse_location_id}},
 *    product_gallery_json {remove, order, main_id, main_pending_index}
 * Both send: every scalar product field, multi_category_ids /
 * multi_subcategory_ids, materiels (combo), packs (feature on: single = flat
 * list, variant = per-variant rows tagged product_variant_id/variant_name),
 * price_tiers (feature on: [{id, min_qty, max_qty, price}]),
 * tags, faqs, gallery_images[], variant_images[i], image.
 * is_variant is derived: true only when type is_variant AND variants exist.
 * Legacy also mirrors the first picked category/subcategory into the legacy
 * scalar category_id / sub_category_id columns (syncLegacyCategoryFields).
 */
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { message, Modal } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import {
  UploadOutlined, LinkOutlined, PlusOutlined, DeleteOutlined, ShopOutlined, ReloadOutlined,
  QuestionCircleOutlined, AppstoreOutlined, TableOutlined, UnorderedListOutlined, EditOutlined,
  ThunderboltOutlined,
} from '@ant-design/icons-vue';
import { Barcode } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AiProductWriterModal from '../../components/ai/AiProductWriterModal.vue';
import { useAiWriter } from '../../composables/useAiWriter';
import { t as tf } from '../../i18n';
import ProductScanModal from '../../components/ProductScanModal.vue';
import ShopifyLinkCard from './ShopifyLinkCard.vue';
import http from '../../lib/http';
import { barcodeValueError, generateBarcodeValue } from '../../lib/barcodeSymbology';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const id = computed(() => route.params.id);
const isEdit = computed(() => !!id.value);

const loadingRecord = ref(true);
const submitting = ref(false);
const formRef = ref();
const shopifyCardRef = ref();
const codeExists = ref('');

const categories = ref([]);
const subcategories = ref([]);
const brands = ref([]);
const sizeGuides = ref([]);
// Smart reordering card: per-product overrides (null = global default).
const reorder = ref({
  preferred_provider_id: null, lead_time_days: null, safety_days: null,
  min_order_qty: null, order_multiple: null, excluded: false,
});
const providers = ref([]);
const reorderDefaults = ref({ lead_time_days: 7, safety_days: 3 });
const units = ref([]);
const subUnits = ref([]);
const warehouses = ref([]);
const locationsByWarehouse = ref({});
const showGtin = ref(true);
const showSerialTracking = ref(false);
const enableMultiPack = ref(false);

const gallery = ref([]);          // {_uid, url, is_main, sort_order, _file?, id?}
const galleryRemoveIds = ref([]);
let gallerySeed = 0;
const galleryUrlInput = ref('');
const isRemoteImage = v => /^https?:\/\//i.test(String(v || ''));

const variants = ref([]);
const variantTag = ref('');
const materiels = ref([]);
const comboProducts = ref([]);
const comboQuery = ref('');
const packs = ref([]);
/* Wholesale Pricing by Quantity — [{id, min_qty, max_qty, price}]; max_qty null
   is the open-ended top bracket ("100+"). Prices are per piece, in the same
   basis as the product's retail price. */
const enableWholesalePricing = ref(false);
const priceTiers = ref([]);
const tags = ref([]);
// Storefront badge labels — free text, with the styled ones suggested.
const labels = ref([]);
const shippingMethods = ref([]);

// Related products: ids drive the select; `relatedKnown` remembers a label for
// every id ever seen, so chips keep their names when a new search replaces the
// result list underneath them.
const relatedIds = ref([]);
const relatedKnown = ref(new Map());
const relatedResults = ref([]);
let relatedTimer = null;
const relatedLabel = p => (p.code ? p.name + ' (' + p.code + ')' : p.name);
const relatedOptions = computed(() => {
  const seen = new Set();
  const out = [];
  const push = (id, label) => {
    if (seen.has(id)) return;
    seen.add(id);
    out.push({ value: id, label });
  };
  relatedIds.value.forEach(id => push(id, relatedKnown.value.get(id) || ('#' + id)));
  relatedResults.value.forEach(p => push(Number(p.id), relatedLabel(p)));
  return out;
});
function onRelatedSearch(q) {
  clearTimeout(relatedTimer);
  relatedTimer = setTimeout(async () => {
    try {
      const r = await http.get('products/search-basic', { q: q || '', exclude: product.value.id || 0 });
      relatedResults.value = r.products || [];
      relatedResults.value.forEach(p => relatedKnown.value.set(Number(p.id), relatedLabel(p)));
    } catch (e) {
      relatedResults.value = [];
    }
  }, 300);
}
const labelSuggestions = ['New', 'Used', 'Refurbished', 'Sale', 'Hot', 'Limited', 'Exclusive']
  .map(v => ({ label: v, value: v }));
const faqs = ref([]);
const warehouseRows = ref({});

const periodUnits = computed(() => [
  { value: 'days', label: t('Days') },
  { value: 'months', label: t('Months') },
  { value: 'years', label: t('Years') },
]);

const product = ref({
  type: 'is_single',
  name: '', code: '', gtin: '', points: 0, Type_barcode: 'CODE128',
  cost: null, price: null, wholesale_price: null, min_price: null,
  brand_id: undefined, category_id: '', sub_category_id: '',
  assigned_category_ids: [], assigned_subcategory_ids: [],
  TaxNet: 0, tax_method: '1', discount_method: '1', discount: 0,
  unit_id: undefined, unit_sale_id: undefined, unit_purchase_id: undefined,
  stock_alert: 0, weight: null, length: null, width: null, height: null,
  // carried through untouched like legacy (server sets it from the gallery's
  // main image); there is no separate main-image picker in either UI
  image: '',
  note: '',
  is_variant: false, is_imei: false, not_selling: false, is_active: true,
  is_featured: false, hide_from_online_store: false, is_returnable: true,
  is_classified: false,
  is_preorder: false, preorder_always: false, preorder_available_date: '',
  preorder_limit: '', preorder_note: '',
  is_batch_tracked: false, shelf_life_days: '',
  generic_name: '', strength: '', dosage_form: '', pack_size: '',
  manufacturer: '', prescription_required: false, drug_schedule: '',
  size_guide_id: null,
  shipping_method_ids: [],
  warranty_period: null, warranty_unit: 'months', warranty_terms: '',
  has_guarantee: false, guarantee_period: null, guarantee_unit: 'months',
});

/* ---------------- options ---------------- */
const categoryOptions = computed(() => categories.value.map(c => ({ value: c.id, label: c.name })));
const subcategoryOptions = computed(() => {
  const picked = new Set((product.value.assigned_category_ids || []).map(String));
  if (!picked.size) return [];
  const catName = cid => (categories.value.find(c => String(c.id) === String(cid)) || {}).name || '';
  return subcategories.value
    .filter(s => picked.has(String(s.category_id)))
    .map(s => ({ value: s.id, label: catName(s.category_id) ? `${s.name} (${catName(s.category_id)})` : s.name }));
});
const brandOptions = computed(() => brands.value.map(b => ({ value: b.id, label: b.name })));
const unitOptions = computed(() => units.value.map(u => ({ value: u.id, label: u.name })));
const subUnitOptions = computed(() => subUnits.value.map(u => ({ value: u.id, label: u.name })));
const comboOptions = computed(() => {
  const q = comboQuery.value.trim().toLowerCase();
  const list = q
    ? comboProducts.value.filter(p =>
      String(p.name || '').toLowerCase().includes(q) || String(p.code || '').toLowerCase().includes(q))
    : comboProducts.value.slice(0, 20);
  return list.map(p => ({ value: p.code, label: `${p.name} (${p.code})` }));
});
const comboTotalCost = computed(() =>
  materiels.value.reduce((s, m) => s + Number(m.cost || 0) * Number(m.quantity || 0), 0));

// Types priced on the product itself. Variants carry their own wholesale/min
// per row in the variants table, so the product-level fields don't apply there.
const hasTieredPricing = computed(() =>
  product.value.type === 'is_single' || product.value.type === 'is_service');

const variantColumns = computed(() => [
  { title: t('Image'), key: 'image', width: 80 },
  { title: t('Code'), key: 'code', width: 130 },
  ...(showGtin.value ? [{ title: t('Barcode_GTIN'), key: 'gtin', width: 130 }] : []),
  // Options mode: one select column per option (Color, Size...) before the generated name.
  ...(variantMode.value === 'options'
    ? options.value
      .filter(o => (o.name || '').trim())
      .map(o => ({ title: o.name, key: `opt:${o.key}`, optionKey: o.key, width: 140 }))
    : []),
  { title: t('Name'), key: 'text', width: 150 },
  { title: t('Cost'), key: 'cost', width: 110 },
  { title: t('Price'), key: 'price', width: 110 },
  { title: t('Wholesale_Price'), key: 'wholesale', width: 120 },
  { title: t('Min_Selling_Price'), key: 'min_price', width: 120 },
  { title: '', key: 'action', width: 50 },
]);
const materielColumns = computed(() => [
  { title: t('Code'), dataIndex: 'code', key: 'code' },
  { title: t('ProductName'), dataIndex: 'name', key: 'name' },
  { title: t('Cost'), dataIndex: 'cost', key: 'cost', align: 'right' },
  { title: t('Quantity'), key: 'quantity', width: 110 },
  { title: t('Total'), key: 'total', align: 'right' },
  { title: '', key: 'action', width: 50 },
]);
const packColumns = computed(() => [
  { title: t('Name'), key: 'name' },
  { title: t('Quantity'), key: 'multiplier', width: 110 },
  { title: t('Price'), key: 'price', width: 130 },
  { title: t('Status'), key: 'is_active', width: 90 },
  { title: '', key: 'action', width: 90 },
]);

const priceTierColumns = computed(() => [
  { title: t('Min_Quantity'), key: 'min_qty', width: 130 },
  { title: t('Max_Quantity'), key: 'max_qty', width: 130 },
  { title: t('Price_Per_Piece'), key: 'price', width: 150 },
  { title: '', key: 'action', width: 60 },
]);

const rules = computed(() => ({
  type: [{ required: true, message: t('Field_is_required') }],
  name: [{ required: true, message: t('Field_is_required') }],
  code: [
    { required: true, message: t('Field_is_required') },
    {
      validator: (_rule, value) => {
        const err = barcodeValueError(product.value.Type_barcode, value);
        return err ? Promise.reject(t(err)) : Promise.resolve();
      },
      trigger: 'change',
    },
  ],
  Type_barcode: [{ required: true, message: t('Field_is_required') }],
  assigned_category_ids: [{ required: true, message: t('Field_is_required') }],
  tax_method: [{ required: true, message: t('Field_is_required') }],
  discount_method: [{ required: true, message: t('Field_is_required') }],
  ...(product.value.type === 'is_service' ? {} : { unit_id: [{ required: true, message: t('Field_is_required') }] }),
  ...(product.value.type === 'is_variant' ? {} : { price: [{ required: true, message: t('Field_is_required') }] }),
  ...(product.value.type === 'is_single' || product.value.type === 'is_combo' ? { cost: [{ required: true, message: t('Field_is_required') }] } : {}),
}));

/* ---------------- behaviour ---------------- */
function generateCode() {
  product.value.code = generateBarcodeValue(product.value.Type_barcode);
  formRef.value?.validate(['code']).catch(() => {});
}

function generateVariantCode(variant) {
  variant.code = generateBarcodeValue(product.value.Type_barcode);
}
function onSymbologyChange() {
  // The same code may be valid for one symbology and not another.
  if (product.value.code) formRef.value?.validate(['code']).catch(() => {});
}
function onCodeScan(code) {
  product.value.code = code;
  codeExists.value = '';
}
function onTypeChange(type) {
  if (type === 'is_combo' && !comboProducts.value.length) loadComboProducts();
  if (type === 'is_single' && enableMultiPack.value) ensureDefaultPack();
  if (type === 'is_variant' && enableMultiPack.value) variants.value.forEach(ensureVariantDefaultPack);
}
function onCategoriesChange() {
  // drop subcategories whose parent category is no longer selected (legacy prune)
  const picked = new Set((product.value.assigned_category_ids || []).map(String));
  product.value.assigned_subcategory_ids = (product.value.assigned_subcategory_ids || [])
    .filter(sid => {
      const sc = subcategories.value.find(s => String(s.id) === String(sid));
      return sc && picked.has(String(sc.category_id));
    });
}
async function loadSubUnits(baseUnitId) {
  if (!baseUnitId) { subUnits.value = []; return; }
  try {
    subUnits.value = (await http.get('get_sub_units_by_base', { id: baseUnitId })) || [];
  } catch (e) { subUnits.value = []; }
}
function onBaseUnitChange(value) {
  product.value.unit_sale_id = undefined;
  product.value.unit_purchase_id = undefined;
  loadSubUnits(value);
}

/* variant options (Shopify-style Color / Size)
 * variantMode 'options': options[] = [{ key, name, values[] }] and every row
 * carries option_values keyed by option key ({ o1: 'Red', o2: 'S' }); the
 * row's text is derived ("Red / S"). The payload converts keys to option
 * names, which is what the backend stores. Legacy products (no options) load
 * in 'list' mode and behave exactly as before. */
const MAX_OPTIONS = 3;
const MAX_VARIANTS = 100;
const variantMode = ref(isEdit.value ? 'list' : 'options');
const variantGuideOpen = ref(false);
// Variable products get the Variants card across the full page width (see template).
const isVariantLayout = computed(() => product.value.type === 'is_variant');
const options = ref([]);
const bulk = ref({ cost: '', price: '' });
let optionKeySeq = 0;
const newOptionKey = () => `o${++optionKeySeq}`;
const optionNameSuggestions = computed(() => ['Color', 'Size', 'Material', 'Style'].map(k => ({ value: t(k) })));
const optionByKey = key => options.value.find(o => o.key === key);
const activeOptions = computed(() => options.value.filter(o => (o.name || '').trim() && (o.values || []).length));
const canGenerate = computed(() => options.value.length > 0 && activeOptions.value.length === options.value.length);

function nextVarId() {
  return variants.value.reduce((m, v) => Math.max(m, Number(v.var_id) || 0), 0) + 1;
}
function variantTitle(ov) {
  return options.value
    .map(o => (ov || {})[o.key])
    .filter(v => v !== undefined && v !== null && String(v).trim() !== '')
    .join(' / ');
}
function variantSignature(row) {
  return options.value.map(o => String(row.option_values?.[o.key] ?? '').trim().toLowerCase()).join('\u0000');
}
function isVariantComplete(row) {
  return options.value.every(o => row.option_values && row.option_values[o.key]);
}
function optionValuesByName(row) {
  const out = {};
  options.value.forEach(o => {
    const v = row.option_values?.[o.key];
    if (v) out[o.name.trim()] = v;
  });
  return out;
}
function refreshVariantTitles() {
  variants.value.forEach(v => { v.text = variantTitle(v.option_values); });
}

function addOption() {
  if (options.value.length >= MAX_OPTIONS) { message.warning(t('Max_Options_Reached')); return; }
  options.value.push({ key: newOptionKey(), name: '', values: [], useSwatches: false, swatches: {} });
}
function removeOption(idx) {
  const opt = options.value[idx];
  const doRemove = () => {
    options.value.splice(idx, 1);
    // Strip the dimension from every row, then merge rows that became the
    // same combination (prefer keeping an already-saved row).
    const seen = new Map();
    const keep = [];
    let dropped = 0;
    variants.value.forEach(v => {
      if (v.option_values) delete v.option_values[opt.key];
      v.text = variantTitle(v.option_values);
      const sig = variantSignature(v);
      const prev = seen.get(sig);
      if (!prev) { seen.set(sig, v); keep.push(v); return; }
      if (!prev.id && v.id) { keep[keep.indexOf(prev)] = v; seen.set(sig, v); }
      dropped++;
    });
    variants.value = keep;
    if (dropped) message.info(t('Variants_Removed', { count: dropped }));
  };
  if (!variants.value.length) { doRemove(); return; }
  Modal.confirm({
    title: t('Remove_Option_Confirm'),
    content: variants.value.some(v => v.id) ? t('Saved_Variants_Removed_On_Save') : undefined,
    okType: 'danger', onOk: doRemove,
  });
}
function onOptionValuesChange(opt, vals) {
  const next = [];
  const seen = new Set();
  (vals || []).forEach(raw => {
    const v = String(raw ?? '').trim();
    if (!v || seen.has(v.toLowerCase())) return;
    seen.add(v.toLowerCase());
    next.push(v);
  });
  const removed = (opt.values || []).filter(v => !seen.has(v.toLowerCase()));
  const affected = removed.length
    ? variants.value.filter(v => removed.some(r => String(v.option_values?.[opt.key] ?? '').toLowerCase() === r.toLowerCase()))
    : [];
  const apply = () => {
    opt.values = next;
    if (affected.length) variants.value = variants.value.filter(v => !affected.includes(v));
  };
  if (!affected.length) { apply(); return; }
  Modal.confirm({
    title: t('Remove_Option_Values_Confirm', { count: affected.length }),
    content: affected.some(v => v.id) ? t('Saved_Variants_Removed_On_Save') : undefined,
    okType: 'danger', onOk: apply,
  });
}
function setVariantOptionValue(row, optKey, val) {
  if (!row.option_values) row.option_values = {};
  if (val === undefined || val === null || val === '') delete row.option_values[optKey];
  else row.option_values[optKey] = val;
  row.text = variantTitle(row.option_values);
}
function generateVariants() {
  const opts = activeOptions.value;
  if (!canGenerate.value) { message.warning(t('Options_Required')); return; }
  let combos = [[]];
  opts.forEach(o => { combos = combos.flatMap(c => o.values.map(v => [...c, [o.key, v]])); });
  if (combos.length > MAX_VARIANTS) { message.error(t('Too_Many_Variants', { max: MAX_VARIANTS })); return; }
  const existing = new Set(variants.value.map(variantSignature));
  const template = variants.value[0];
  let added = 0;
  combos.forEach(pairs => {
    const ov = Object.fromEntries(pairs);
    const probe = { option_values: ov };
    const sig = variantSignature(probe);
    if (existing.has(sig)) return;
    const row = {
      var_id: nextVarId(), text: variantTitle(ov), option_values: ov,
      code: generateBarcodeValue(product.value.Type_barcode), gtin: '',
      cost: template?.cost ?? '', price: template?.price ?? '',
      wholesale: template?.wholesale ?? '', min_price: template?.min_price ?? '',
    };
    if (enableMultiPack.value) ensureVariantDefaultPack(row);
    variants.value.push(row);
    existing.add(sig);
    added++;
  });
  message.success(added ? t('Variants_Generated', { count: added }) : t('No_New_Combinations'));
}
function applyBulk(field) {
  const val = bulk.value[field];
  if (val === '' || val === null || val === undefined) return;
  variants.value.forEach(v => { v[field] = val; });
  bulk.value[field] = '';
}
function switchVariantMode(mode) {
  if (mode === variantMode.value) return;
  if (mode === 'list') {
    const go = () => {
      variantMode.value = 'list';
      options.value = [];
      variants.value.forEach(v => { delete v.option_values; });
    };
    if (options.value.length) Modal.confirm({ title: t('Switch_To_List_Confirm'), onOk: go });
    else go();
    return;
  }
  variantMode.value = 'options';
  if (!options.value.length) addOption();
}
// A fresh variable product starts with one empty option to fill in.
watch(() => product.value.type, type => {
  if (type === 'is_variant' && variantMode.value === 'options' && !options.value.length && !isEdit.value) addOption();
});
// Option order / names drive the derived titles.
watch(() => options.value.map(o => o.key).join('|'), () => { if (variantMode.value === 'options') refreshVariantTitles(); });

/* variants */
function addVariant() {
  const tag = (variantTag.value || '').trim();
  if (!tag) { message.warning(t('Prod_Enter_Variant')); return; }
  if (variants.value.some(v => v.text === tag)) { message.warning(t('VariantDuplicate')); return; }
  const row = {
    var_id: nextVarId(), text: tag,
    code: '', gtin: '', cost: '', price: '', wholesale: '', min_price: '',
  };
  if (enableMultiPack.value) ensureVariantDefaultPack(row);
  variants.value.push(row);
  variantTag.value = '';
}

// Opening stock per (variant, warehouse) — create mode only. Quantities hang
// on the variant row itself so deleting a variant drops its stock with it.
function setVariantOpening(variant, warehouseId, value) {
  if (!variant.opening) variant.opening = {};
  variant.opening[warehouseId] = value;
}

function pickVariantImage(variant, file) {
  if (!file.type || file.type.indexOf('image/') !== 0) {
    message.warning(t('Please_select_image'));
    return;
  }
  variant.imageFile = file;
  variant.imagePreview = URL.createObjectURL(file);
}

/* combo */
async function loadComboProducts() {
  try {
    comboProducts.value = (await http.get('get_products_materiels')) || [];
  } catch (e) { comboProducts.value = []; }
}
function onComboSearch(v) { comboQuery.value = v || ''; }
function addMateriel(code) {
  const found = comboProducts.value.find(p => p.code === code);
  if (!found) return;
  if (materiels.value.some(m => m.code === found.code)) {
    message.warning(t('Prod_Product_Already_Added'));
    return;
  }
  materiels.value.push({
    product_id: found.product_id, name: found.name, code: found.code,
    unit_name: found.unit_name, cost: found.cost, quantity: 1,
  });
  comboQuery.value = '';
}

/* packs — exactly one default pack (multiplier 1, always active) on top.
   Single products keep one flat list (packs); variant products keep one list
   per variant (v.packs). */
function ensureDefaultPack() {
  let def = packs.value.find(p => p.is_default);
  if (!def) {
    def = { id: null, name: t('Default'), multiplier: 1, price: product.value.price || 0, is_active: true, is_default: true };
    packs.value.unshift(def);
  }
  def.multiplier = 1;
  def.is_active = true;
}
function addPack() {
  ensureDefaultPack();
  packs.value.push({ id: null, name: '', multiplier: 1, price: 0, is_active: true, is_default: false });
}
function ensureVariantDefaultPack(v) {
  if (!Array.isArray(v.packs)) v.packs = [];
  let def = v.packs.find(p => p.is_default);
  if (!def) {
    def = { id: null, name: t('Default'), multiplier: 1, price: Number(v.price) || 0, is_active: true, is_default: true };
    v.packs.unshift(def);
  }
  def.multiplier = 1;
  def.is_active = true;
}
/* Each new bracket starts where the previous one ended, so the ladder builds
   itself in order and the backend's overlap check has nothing to drop. */
function addPriceTier() {
  const last = priceTiers.value[priceTiers.value.length - 1];
  const nextMin = last ? Math.max(Number(last.max_qty || last.min_qty || 0) + 1, 1) : 10;
  priceTiers.value.push({ id: null, min_qty: nextMin, max_qty: null, price: product.value.price || 0 });
}

function addVariantPack(v) {
  ensureVariantDefaultPack(v);
  v.packs.push({ id: null, name: '', multiplier: 1, price: 0, is_active: true, is_default: false });
}

/* gallery */
function addGalleryFile(f) {
  gallery.value.push({
    _uid: `n-${gallerySeed++}`, url: URL.createObjectURL(f),
    is_main: false, sort_order: gallery.value.length, _file: f,
  });
  if (!gallery.value.some(g => g.is_main)) gallery.value[0].is_main = true;
}
function addGalleryUrl() {
  const url = String(galleryUrlInput.value || '').trim();
  if (!url) return;
  if (!/^https?:\/\/\S+$/i.test(url) || url.length > 255) {
    message.error(t('Invalid_image_link'));
    return;
  }
  if (gallery.value.some(g => (g._url || g.image_path) === url)) {
    galleryUrlInput.value = '';
    return;
  }
  gallery.value.push({
    _uid: `n-${gallerySeed++}`, url,
    is_main: false, sort_order: gallery.value.length, _url: url,
  });
  if (!gallery.value.some(g => g.is_main)) gallery.value[0].is_main = true;
  galleryUrlInput.value = '';
}
function onGalleryImgError(g) {
  if (g && (g._url || g.remote)) g.url = '/images/products/no-image.png';
}
function setMain(i) {
  gallery.value.forEach((g, idx) => { g.is_main = idx === i; });
}
function moveGallery(i, dir) {
  const j = i + dir;
  if (j < 0 || j >= gallery.value.length) return;
  const [row] = gallery.value.splice(i, 1);
  gallery.value.splice(j, 0, row);
}
function removeGallery(i) {
  const row = gallery.value[i];
  if (row.id) galleryRemoveIds.value.push(row.id);
  if (row.url && row.url.startsWith('blob:')) {
    try { URL.revokeObjectURL(row.url); } catch (e) { /* ignore */ }
  }
  gallery.value.splice(i, 1);
  if (gallery.value.length && !gallery.value.some(g => g.is_main)) gallery.value[0].is_main = true;
}

/* quick-create modals */
const quickBusy = ref(false);
const quickCategoryOpen = ref(false);
const quickBrandOpen = ref(false);
const quickUnitOpen = ref(false);
const quickLocationOpen = ref(false);
const quickCategory = ref({ name: '', code: '' });
const quickBrand = ref({ name: '', description: '' });
const quickUnit = ref({ name: '', ShortName: '', base_unit: '', operator: '*', operator_value: 1 });
const quickLocation = ref({ warehouse_id: '', code: '', name: '', is_active: true });

function openQuickCategory() { quickCategory.value = { name: '', code: '' }; quickCategoryOpen.value = true; }
function openQuickBrand() { quickBrand.value = { name: '', description: '' }; quickBrandOpen.value = true; }
function openQuickUnit() { quickUnit.value = { name: '', ShortName: '', base_unit: null, operator: '*', operator_value: 1 }; quickUnitOpen.value = true; }
function openQuickLocation(warehouseId) {
  quickLocation.value = { warehouse_id: warehouseId, code: '', name: '', is_active: true };
  quickLocationOpen.value = true;
}
async function saveQuickCategory() {
  if (!quickCategory.value.name) { message.error(t('Field_is_required')); return; }
  quickBusy.value = true;
  try {
    const resp = await http.post('categories', quickCategory.value);
    const list = await http.get('categories', { limit: -1 });
    categories.value = list.categories || list.data || list || [];
    // Select the new category right away (multi-select: append).
    const created = resp?.category
      || categories.value.find(c => c.name === quickCategory.value.name && c.code === quickCategory.value.code);
    if (created?.id) {
      const ids = product.value.assigned_category_ids || (product.value.assigned_category_ids = []);
      if (!ids.includes(created.id)) ids.push(created.id);
    }
    quickCategoryOpen.value = false;
    message.success(t('Successfully_Created'));
  } catch (e) { message.error(t('InvalidData')); } finally { quickBusy.value = false; }
}
async function saveQuickBrand() {
  if (!quickBrand.value.name) { message.error(t('Field_is_required')); return; }
  quickBusy.value = true;
  try {
    const resp = await http.post('brands', quickBrand.value);
    const list = await http.get('brands', { limit: -1, SortField: 'id', SortType: 'desc' });
    brands.value = list.brands || list.data || list || [];
    // Select the new brand right away.
    const created = resp?.brand || brands.value.find(b => b.name === quickBrand.value.name);
    if (created?.id) product.value.brand_id = created.id;
    quickBrandOpen.value = false;
    message.success(t('Successfully_Created'));
  } catch (e) { message.error(t('InvalidData')); } finally { quickBusy.value = false; }
}
async function saveQuickUnit() {
  if (!quickUnit.value.name || !quickUnit.value.ShortName) { message.error(t('Field_is_required')); return; }
  quickBusy.value = true;
  try {
    // Legacy sends '' (not null) when there is no base unit — same as Units.vue.
    const resp = await http.post('units', { ...quickUnit.value, base_unit: quickUnit.value.base_unit || '' });
    const data = await http.get('products/create');
    units.value = data.units || [];
    const created = resp?.unit
      || units.value.find(u => u.name === quickUnit.value.name && u.ShortName === quickUnit.value.ShortName);
    if (created?.id) {
      if (!quickUnit.value.base_unit) {
        // New BASE unit → select it as the product unit (clears sale/purchase
        // sub-units like a manual change would).
        product.value.unit_id = created.id;
        onBaseUnitChange(created.id);
      } else if (String(product.value.unit_id) === String(quickUnit.value.base_unit)) {
        // New sub-unit of the currently selected base → refresh the
        // sale/purchase options so it is offered immediately.
        loadSubUnits(product.value.unit_id);
      }
    }
    quickUnitOpen.value = false;
    message.success(t('Successfully_Created'));
  } catch (e) { message.error(t('InvalidData')); } finally { quickBusy.value = false; }
}
async function saveQuickLocation() {
  if (!quickLocation.value.code) { message.error(t('Field_is_required')); return; }
  quickBusy.value = true;
  try {
    const data = await http.post('products/warehouse_locations', quickLocation.value);
    const loc = data.location || data;
    const wid = quickLocation.value.warehouse_id;
    const label = loc.name ? `${loc.code} - ${loc.name}` : loc.code;
    if (!locationsByWarehouse.value[wid]) locationsByWarehouse.value[wid] = [];
    locationsByWarehouse.value[wid].push({ id: loc.id, label });
    quickLocationOpen.value = false;
    message.success(t('Successfully_Created'));
  } catch (e) { message.error(t('InvalidData')); } finally { quickBusy.value = false; }
}

/* ---------------- save ---------------- */
function syncLegacyCategoryFields() {
  const cats = product.value.assigned_category_ids || [];
  const subs = product.value.assigned_subcategory_ids || [];
  product.value.category_id = cats.length ? cats[0] : '';
  product.value.sub_category_id = subs.length ? subs[0] : '';
}

async function submit() {
  try {
    await formRef.value.validate();
  } catch (e) {
    message.error(t('Please_fill_the_form_correctly'));
    return;
  }
  const usesOptions = product.value.type === 'is_variant' && variantMode.value === 'options';
  if (usesOptions) {
    if (!canGenerate.value) { message.error(t('Options_Required')); return; }
    if (variants.value.some(v => !isVariantComplete(v))) { message.error(t('Variant_Missing_Option_Values')); return; }
    if (variants.value.length > MAX_VARIANTS) { message.error(t('Too_Many_Variants', { max: MAX_VARIANTS })); return; }
  }
  submitting.value = true;
  syncLegacyCategoryFields();
  product.value.is_variant = product.value.type === 'is_variant' && variants.value.length > 0;

  const fd = new FormData();
  const { assigned_category_ids: cats, assigned_subcategory_ids: subs, ...rest } = product.value;
  Object.entries(rest).forEach(([k, v]) => fd.append(k, v === null || v === undefined ? '' : v));
  fd.append('multi_category_ids', JSON.stringify(cats || []));
  fd.append('multi_subcategory_ids', JSON.stringify(subs || []));

  if (materiels.value.length && product.value.type === 'is_combo') {
    fd.append('materiels', JSON.stringify(materiels.value));
  }

  // Variants: create sends one JSON blob, edit sends bracket fields (legacy).
  // Packs never travel inside the variants payload — they go in "packs" below.
  // Options mode adds `options` ([{name, values}]) and per-row option_values
  // keyed by option NAME; no `options` key = legacy flat list (and clears any
  // options the product had).
  if (usesOptions) {
    fd.append('options', JSON.stringify(options.value.map(o => ({
      name: o.name.trim(),
      values: o.useSwatches
        ? o.values.map(v => ({ value: v, swatch: (o.swatches && o.swatches[v]) || null }))
        : o.values,
    }))));
  }
  const variantPayloadRow = v => {
    const { imageFile, imagePreview, packs: _p, opening: _o, option_values: _ov, ...r } = v;
    if (usesOptions) r.option_values = optionValuesByName(v);
    return r;
  };
  if (variants.value.length) {
    if (isEdit.value) {
      variants.value.forEach((v, i) => {
        Object.entries(variantPayloadRow(v)).forEach(([k, val]) => {
          if (k === 'option_values') { fd.append(`variants[${i}][option_values]`, JSON.stringify(val || {})); return; }
          fd.append(`variants[${i}][${k}]`, val === null || val === undefined ? '' : val);
        });
        if (v.imageFile) fd.append(`variant_images[${i}]`, v.imageFile);
      });
    } else {
      fd.append('variants', JSON.stringify(variants.value.map(variantPayloadRow)));
      variants.value.forEach((v, i) => {
        if (v.imageFile) fd.append(`variant_images[${i}]`, v.imageFile);
      });
    }
  }

  // Multi-Pack Selling: single products send the flat list; variant products
  // send every variant's rows tagged with the variant id (existing) and name
  // (so packs of variants created in this same submit can be resolved).
  if (enableMultiPack.value) {
    if (product.value.type === 'is_single') {
      ensureDefaultPack();
      fd.append('packs', JSON.stringify(packs.value));
    } else if (product.value.type === 'is_variant' && variants.value.length) {
      const rows = [];
      variants.value.forEach(v => {
        ensureVariantDefaultPack(v);
        v.packs.forEach(pk => rows.push({ ...pk, product_variant_id: v.id || null, variant_name: v.text }));
      });
      fd.append('packs', JSON.stringify(rows));
    }
  }

  // Wholesale Pricing by Quantity: send the ladder as typed — the backend
  // sanitises, sorts and de-overlaps it.
  if (enableWholesalePricing.value) {
    fd.append('price_tiers', JSON.stringify(priceTiers.value));
  }

  fd.append('size_guide_id', product.value.size_guide_id || '');
  fd.append('tags', JSON.stringify(tags.value || []));
  fd.append('labels', JSON.stringify(labels.value || []));
  // Empty = no restriction, which is what an unedited product keeps.
  fd.append('shipping_method_ids', JSON.stringify(product.value.shipping_method_ids || []));
  // Order matters — the storefront shows them exactly as arranged here.
  fd.append('related_product_ids', JSON.stringify(relatedIds.value || []));
  fd.append('faqs', JSON.stringify(faqs.value || []));
  fd.append('reorder', JSON.stringify(reorder.value));

  if (isEdit.value) {
    const wl = {};
    warehouses.value.forEach(w => {
      wl[w.id] = { warehouse_location_id: warehouseRows.value[w.id]?.warehouse_location_id || null };
    });
    fd.append('warehouse_locations', JSON.stringify(wl));
  } else if (product.value.type === 'is_single' && Object.keys(warehouseRows.value).length) {
    fd.append('warehouses', JSON.stringify(warehouseRows.value));
  } else if (product.value.type === 'is_variant' && variants.value.length) {
    // Opening stock per variant: { warehouse_id: { var_id: qty } } — keyed by
    // the row's var_id so it survives rows being generated / removed.
    const vo = {};
    warehouses.value.forEach(w => {
      vo[w.id] = {};
      variants.value.forEach(v => { vo[w.id][v.var_id] = Number(v.opening?.[w.id]) || 0; });
    });
    fd.append('variant_opening', JSON.stringify(vo));
  }

  // Gallery — payload shape differs between create and edit (legacy).
  // New entries = uploaded files + pasted links; the manifest keeps their
  // combined order so the backend creates rows in the arranged order.
  const pendingAll = gallery.value.filter(g => g._file || g._url);
  pendingAll.forEach(g => {
    if (g._file) fd.append('gallery_images[]', g._file);
    else if (g._url) fd.append('gallery_urls[]', g._url);
  });
  if (pendingAll.length) {
    fd.append('gallery_new_manifest', JSON.stringify(pendingAll.map(g => ({ kind: g._file ? 'file' : 'url' }))));
  }
  if (isEdit.value) {
    const order = gallery.value.filter(g => g.id).map((g, i) => ({ id: g.id, sort_order: i }));
    const mainRow = gallery.value.find(g => g.is_main);
    const pending = pendingAll;
    const hasChanges = galleryRemoveIds.value.length > 0 || order.length > 0 || pending.length > 0;
    if (hasChanges) {
      fd.append('product_gallery_json', JSON.stringify({
        remove: galleryRemoveIds.value,
        order,
        main_id: mainRow && mainRow.id ? mainRow.id : null,
        main_pending_index: mainRow && !mainRow.id ? pending.indexOf(mainRow) : null,
      }));
    }
  } else if (gallery.value.length) {
    let mainIndex = gallery.value.findIndex(g => g.is_main);
    if (mainIndex < 0) mainIndex = 0;
    fd.append('product_gallery_json', JSON.stringify({ main_index: mainIndex }));
  }

  try {
    if (isEdit.value) {
      fd.append('_method', 'put');
      await http.postForm(`products/${id.value}`, fd);
      message.success(t('Successfully_Updated'));
    } else {
      const created = await http.postForm('products', fd);
      message.success(t('Successfully_Created'));
      // A Shopify listing picked before the product existed is paired now
      // that it has an id. A failure here must not look like a failed save.
      if (created?.id && shopifyCardRef.value?.hasPending()) {
        const linked = await shopifyCardRef.value.commit(created.id);
        if (!linked) message.warning(t('Shopify_Link_After_Save_Failed'));
      }
    }
    router.push('/products');
  } catch (e) {
    const errors = e?.data?.errors;
    if (errors?.code?.length) {
      codeExists.value = errors.code[0];
      message.error(errors.code[0]);
    } else if (errors?.variants?.length) {
      message.error(errors.variants[0]);
    } else if (errors) {
      Object.values(errors).flat().forEach(m => message.error(String(m)));
    } else {
      message.error(e?.data?.message || t('InvalidData'));
    }
  } finally {
    submitting.value = false;
  }
}

/* ---------------- bootstrap ---------------- */
function indexLocations(list) {
  const by = {};
  (list || []).forEach(loc => {
    const wid = loc.warehouse_id;
    if (!by[wid]) by[wid] = [];
    by[wid].push({ id: loc.id, label: loc.name ? `${loc.code} - ${loc.name}` : loc.code });
  });
  locationsByWarehouse.value = by;
}

/* ---------------- AI product writer ---------------- */
const { options: aiWriterOptions, load: loadAiWriter } = useAiWriter();
const aiWriterReady = computed(() => !!aiWriterOptions.value?.ready);
const aiWriterOpen = ref(false);

/** Facts for the AI from the form as it is now (unsaved edits included). Cost is never sent. */
function aiFacts() {
  const p = product.value;
  const labelOf = (opts, value) => (opts.find(o => String(o.value) === String(value)) || {}).label;
  return {
    name: p.name,
    code: p.code,
    type: { is_single: 'standard product', is_variant: 'product with variants', is_service: 'service', is_combo: 'bundle' }[p.type] || p.type,
    categories: (p.assigned_category_ids || []).map(v => labelOf(categoryOptions.value, v)).filter(Boolean),
    subcategories: (p.assigned_subcategory_ids || []).map(v => labelOf(subcategoryOptions.value, v)).filter(Boolean),
    brand: labelOf(brandOptions.value, p.brand_id),
    unit: labelOf(unitOptions.value, p.unit_id),
    variants: variants.value.map(v => v.text).filter(Boolean),
    weight: p.weight,
    dimensions: { length: p.length, width: p.width, height: p.height },
    warranty: p.warranty_period ? `${p.warranty_period} ${p.warranty_unit}` : null,
    warranty_terms: p.warranty_terms,
    generic_name: p.generic_name,
    strength: p.strength,
    dosage_form: p.dosage_form,
    pack_size: p.pack_size,
    manufacturer: p.manufacturer,
    labels: labels.value,
    existing_tags: tags.value,
    existing_description: p.note,
  };
}

/** Description replaces the note; tags and FAQs are added to what's there. */
function applyAiDraft(draft) {
  if (draft.description) product.value.note = draft.description;
  if (draft.tags?.length) tags.value = [...new Set([...tags.value, ...draft.tags])];
  if (draft.faqs?.length) faqs.value.push(...draft.faqs.map(f => ({ question: f.question, answer: f.answer })));
  message.success(tf('Ai_Applied', 'Draft applied — review it and save the product.'));
}

loadAiWriter();

onMounted(async () => {
  try {
    if (isEdit.value) {
      const data = await http.get(`products/${id.value}/edit`);
      const p = data.product || {};
      categories.value = data.categories || [];
      subcategories.value = data.all_subcategories || data.subcategories || [];
      brands.value = data.brands || [];
      sizeGuides.value = data.size_guides || [];
      providers.value = data.providers || [];
      if (data.reorder_defaults) reorderDefaults.value = data.reorder_defaults;
      if (data.reorder) reorder.value = { ...reorder.value, ...data.reorder };
      shippingMethods.value = data.shipping_methods || [];
      units.value = data.units || [];
      subUnits.value = data.units_sub || [];
      warehouses.value = data.warehouses || [];
      indexLocations(data.warehouse_locations);
      showGtin.value = data.show_product_gtin !== false;
      showSerialTracking.value = data.show_serial_tracking === true;
      enableMultiPack.value = data.enable_multi_pack_selling === true;
      enableWholesalePricing.value = data.enable_wholesale_pricing === true;

      product.value = { ...product.value, ...p };
      // legacy: seed the multi-selects from the scalar columns when empty
      if (!Array.isArray(product.value.assigned_category_ids) || !product.value.assigned_category_ids.length) {
        product.value.assigned_category_ids = p.category_id ? [p.category_id] : [];
      }
      if (!Array.isArray(product.value.assigned_subcategory_ids) || !product.value.assigned_subcategory_ids.length) {
        product.value.assigned_subcategory_ids = p.sub_category_id ? [p.sub_category_id] : [];
      }
      onCategoriesChange();

      // Options: products with product_options rows open in options mode,
      // everything else (legacy flat list) in list mode.
      const loadedOptions = (p.options || []).map(o => ({
        key: newOptionKey(), name: o.name, values: (o.values || []).map(x => x.value),
        useSwatches: (o.values || []).some(x => x.swatch),
        swatches: Object.fromEntries((o.values || []).filter(x => x.swatch).map(x => [x.value, x.swatch])),
      }));
      options.value = loadedOptions;
      variantMode.value = loadedOptions.length ? 'options' : 'list';
      variants.value = (p.ProductVariant || []).map(v => {
        const { option_values: byName, ...rest } = v;
        const row = { ...rest };
        if (loadedOptions.length) {
          row.option_values = {};
          loadedOptions.forEach(o => {
            const val = byName && byName[o.name];
            if (val) row.option_values[o.key] = val;
          });
        }
        return row;
      });
      const packRows = (p.packs || []).map(x => ({
        id: x.id, name: x.name, multiplier: Number(x.multiplier), price: Number(x.price),
        is_active: !!x.is_active, is_default: !!x.is_default,
        product_variant_id: x.product_variant_id || null,
      }));
      if (product.value.type === 'is_variant') {
        // Per-variant lists: hang each pack on its variant row.
        variants.value.forEach(v => {
          v.packs = packRows.filter(x => x.product_variant_id === v.id);
          if (enableMultiPack.value) ensureVariantDefaultPack(v);
        });
      } else {
        packs.value = packRows.filter(x => !x.product_variant_id);
        if (enableMultiPack.value && product.value.type === 'is_single') ensureDefaultPack();
      }
      priceTiers.value = (p.price_tiers || []).map(x => ({
        id: x.id,
        min_qty: Number(x.min_qty),
        max_qty: x.max_qty === null || x.max_qty === undefined ? null : Number(x.max_qty),
        price: Number(x.price),
      }));
      tags.value = Array.isArray(p.tags) ? p.tags.slice() : [];
      labels.value = Array.isArray(p.labels) ? p.labels.slice() : [];
      product.value.shipping_method_ids = Array.isArray(p.shipping_method_ids)
        ? p.shipping_method_ids.map(Number) : [];
      const rel = Array.isArray(p.related_products) ? p.related_products : [];
      rel.forEach(r => relatedKnown.value.set(Number(r.id), relatedLabel(r)));
      relatedIds.value = rel.map(r => Number(r.id));
      faqs.value = Array.isArray(p.faqs)
        ? p.faqs.map(f => ({ question: f.question || '', answer: f.answer || '' })) : [];
      gallery.value = (p.product_images || []).map(r => ({
        _uid: `e-${r.id}`, id: r.id,
        url: r.url || r.image_url || (isRemoteImage(r.image_path) ? r.image_path : `/images/products/${r.image_path || r.image}`),
        image_path: r.image_path, remote: isRemoteImage(r.image_path),
        is_main: !!r.is_main, sort_order: r.sort_order,
      }));
      if (p.type === 'is_combo') {
        materiels.value = data.materiels || [];
        loadComboProducts();
      }

      const existing = {};
      (data.product_warehouse_locations || []).forEach(r => {
        existing[r.warehouse_id] = r.warehouse_location_id || null;
      });
      warehouseRows.value = Object.fromEntries(
        warehouses.value.map(w => [w.id, { qte: w.qte ?? 0, warehouse_location_id: existing[w.id] || null }]));

      if (product.value.unit_id && !subUnits.value.length) await loadSubUnits(product.value.unit_id);
    } else {
      const data = await http.get('products/create');
      categories.value = data.categories || [];
      subcategories.value = data.subcategories || [];
      brands.value = data.brands || [];
      sizeGuides.value = data.size_guides || [];
      providers.value = data.providers || [];
      if (data.reorder_defaults) reorderDefaults.value = data.reorder_defaults;
      if (data.reorder) reorder.value = { ...reorder.value, ...data.reorder };
      shippingMethods.value = data.shipping_methods || [];
      units.value = data.units || [];
      warehouses.value = data.warehouses || [];
      indexLocations(data.warehouse_locations);
      showGtin.value = data.show_product_gtin !== false;
      showSerialTracking.value = data.show_serial_tracking === true;
      enableMultiPack.value = data.enable_multi_pack_selling === true;
      enableWholesalePricing.value = data.enable_wholesale_pricing === true;
      if (enableMultiPack.value) ensureDefaultPack();
      warehouseRows.value = Object.fromEntries(
        warehouses.value.map(w => [w.id, { qte: w.qte ?? 0, warehouse_location_id: null }]));
    }
  } catch (e) {
    message.error(t('InvalidData'));
    router.push('/products');
    return;
  } finally {
    loadingRecord.value = false;
  }
});
</script>

<style scoped>
/* "Write with AI" next to the description label */
.ai-write-btn { height: auto; padding: 0; margin-inline-start: 10px; }

/* Variants guide drawer */
.guide-section + .guide-section {
  margin-top: 4px;
}
.guide-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  margin-bottom: 8px;
}
.guide-intro {
  font-size: 13px;
  line-height: 1.6;
  margin: 0 0 10px;
  opacity: 0.85;
}
.guide-divider {
  margin: 16px 0;
}
.guide-steps {
  margin: 0;
  padding-inline-start: 22px;
  font-size: 13px;
  line-height: 1.6;
}
.guide-steps li + li {
  margin-top: 6px;
}
.guide-list {
  list-style: none;
  padding: 0;
  margin: 0;
  font-size: 13px;
  line-height: 1.6;
}
.guide-list li {
  position: relative;
  padding: 4px 0 4px 16px;
}
.guide-list li::before {
  content: '';
  position: absolute;
  left: 2px;
  top: 12px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.5;
}
/* Variant options editor (Color / Size rows above the variants table). */
.variant-options-hint {
  font-size: 12px;
  color: rgba(0, 0, 0, 0.45);
  margin-bottom: 10px;
}
.variant-option-row {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  margin-bottom: 8px;
}
.variant-option-name {
  flex: 0 0 180px;
  width: 180px;
}
.variant-option-values {
  flex: 1;
  min-width: 0;
}
.variant-option-block {
  margin-bottom: 4px;
}
.variant-option-swatch-toggle {
  white-space: nowrap;
  align-self: center;
}
.variant-swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin: 0 0 10px 188px;
}
.variant-swatch {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  cursor: pointer;
}
.variant-swatch input {
  width: 22px;
  height: 22px;
  padding: 0;
  border: 1px solid rgba(0, 0, 0, 0.15);
  border-radius: 4px;
  background: none;
}
.variant-options-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}
.variant-bulk {
  display: flex;
  gap: 6px;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 10px;
}
.variant-bulk-label {
  font-size: 12px;
  color: rgba(0, 0, 0, 0.65);
}
.variant-bulk-input {
  width: 110px;
}
@media (max-width: 640px) {
  .variant-option-row {
    flex-wrap: wrap;
  }
  .variant-option-name {
    flex-basis: 100%;
    width: 100%;
  }
  .variant-swatches {
    margin-left: 0;
  }
}
.quick-add {
  font-size: 12px;
  display: inline-block;
  margin-top: 4px;
}
/* Lucide barcode icon inside the "generate code" text button: center the raw SVG like an anticon. */
.variant-code-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.variant-code-btn svg {
  display: block;
}
.variant-thumb {
  width: 40px;
  height: 40px;
  object-fit: cover;
  border-radius: 6px;
  cursor: pointer;
}
.variant-pack-block {
  margin-bottom: 16px;
}
.variant-pack-block:last-child {
  margin-bottom: 0;
}
.variant-pack-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
/* Opening stock — one tile per warehouse, auto-flowing responsive grid. */
.wh-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 12px;
}
.wh-tile {
  border: 1px solid rgba(128, 128, 128, 0.22);
  border-radius: 10px;
  padding: 12px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.wh-tile:hover {
  border-color: rgba(var(--app-primary-rgb, 109, 40, 217), 0.5);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}
.wh-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
}
.wh-icon {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: rgba(var(--app-primary-rgb, 109, 40, 217), 0.12);
  color: var(--app-primary, #6d28d9);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  flex: none;
}
.wh-name {
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.wh-fields {
  display: flex;
  gap: 8px;
}
.wh-qty {
  width: 90px;
  flex: none;
}
/* Variant opening stock: one "name + qty" row per variant inside the tile. */
.wh-variants {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.wh-variant-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.wh-variant-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 13px;
}
.wh-loc {
  flex: 1;
  min-width: 0;
}
.faq-row {
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
}
.gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
  gap: 10px;
  margin-top: 12px;
}
.gallery-tile {
  position: relative;
  border: 1px solid rgba(5, 5, 5, 0.1);
  border-radius: 8px;
  padding: 4px;
}
.gallery-link-row {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}
.gallery-link-hint {
  font-size: 12px;
  opacity: 0.65;
  margin: 4px 0 8px;
}
.gallery-link-badge {
  position: absolute;
  top: 6px;
  left: 6px;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  font-size: 12px;
}
.gallery-tile.main {
  border-color: #1677ff;
  box-shadow: 0 0 0 1px #1677ff inset;
}
.gallery-tile img {
  width: 100%;
  height: 70px;
  object-fit: cover;
  border-radius: 5px;
  display: block;
}
.gallery-actions {
  display: flex;
  gap: 2px;
  margin-top: 4px;
}
.gallery-actions :deep(.ant-btn) {
  flex: 1;
  padding: 0;
  min-width: 0;
  font-size: 11px;
  height: 22px;
}

.reorder-help {
  font-size: 12px;
  opacity: 0.65;
  margin: -4px 0 12px;
}
</style>
