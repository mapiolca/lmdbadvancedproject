#!/usr/bin/env python3
"""Verify the core symbols and schemas consumed by shipment costs in v20–v24."""
import argparse
from pathlib import Path
import subprocess
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--repository',type=Path,required=True)
args=parser.parse_args()
def read(tag,path):
    return subprocess.check_output(['git','-C',str(args.repository),'show',tag+':htdocs/'+path],text=True)
for version in range(20,25):
    tag=str(version)+'.0.0'
    sha=subprocess.check_output(['git','-C',str(args.repository),'rev-parse',tag+'^{commit}'],text=True).strip()
    shipment=read(tag,'expedition/class/expedition.class.php')
    for symbol in ['SHIPPING_VALIDATE','SHIPPING_CANCEL','SHIPPING_DELETE','SHIPMENT_UNVALIDATE']:
        assert symbol in shipment,(tag,symbol)
    supplier=read(tag,'fourn/class/fournisseur.product.class.php')
    prefix='PRODUCT_BUYPRICE_' if version>=23 else 'SUPPLIER_PRODUCT_BUYPRICE_'
    for event in ['CREATE','MODIFY','DELETE']:
        assert prefix+event in supplier,(tag,prefix+event)
    for table,columns in {'expedition':['date_valid','date_expedition','fk_projet','fk_statut','entity'], 'expeditiondet':['fk_product','fk_elementdet','element_type','qty'], 'product_fournisseur_price':['unitprice','quantity','remise_percent','remise','datec','entity','fk_supplier_price_expression'], 'product_fournisseur_price_log':['fk_product_fournisseur','datec'], 'product':['pmp','fk_unit']}.items():
        schema=read(tag,'install/mysql/tables/llx_'+table+'.sql')
        for column in columns:
            assert column in schema,(tag,table,column)
    assert 'remise' not in read(tag,'install/mysql/tables/llx_product_fournisseur_price_log.sql')
    form=read(tag,'core/class/html.form.class.php')
    for symbol in ['function selectarray','function multiselectarray','function multiSelectArrayWithCheckbox','function selectDate']:
        assert symbol in form,(tag,symbol)
    modules=read(tag,'core/modules/DolibarrModules.class.php')
    assert "isset($value['data']) && is_array($value['data'])" in modules
    assert 'new TCPDF($pagetype, $metric, $format' in read(tag,'core/lib/pdf.lib.php')
    print(tag,sha,'native event, schema and UI contracts present')
