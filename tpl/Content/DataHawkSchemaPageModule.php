		<section>
			<div class="frame">
				<div id="datahawkschema"></div>
			</div>
		</section>

		<script>
			document.addEventListener('DOMContentLoaded', () => {
				(async () => {
					await AssetLoader.loadScriptAsync('plugin/DataHawk/assets/dbdesigner/dbdesigner.js');
					console.log('JqueryDataTable loaded');

					var data = {"data":[{"id":1742300776183,"name":"Person","fields":[{"name":"id","type":"INT"},{"name":"produkt_id","type":"INT"},{"name":"lastname","type":"TEXT"},{"name":"firstname","type":"TEXT"}],"primaryKeys":["id"],"position":{"x":489,"y":184}},{"id":1742300895578,"name":"Produkt","fields":[{"name":"id","type":"INT"},{"name":"name","type":"TEXT"}],"primaryKeys":[],"position":{"x":151,"y":168}}],"foreignKeys":[{"from":{"tableId":1742300776183,"tableName":"Person","fieldName":"produkt_id"},"to":{"tableId":1742300895578,"tableName":"Produkt","fieldName":"id"}}]};
					$('#datahawkschema').dbdesigner().initializeFromData(data);
				})();
			});
		</script>

		<style>
			#datahawkschema { height:600px; border-radius:5px; box-shadow:0 0 10px #ddd; }
			#datahawkschema * { line-height:1.2em; }
		</style>

