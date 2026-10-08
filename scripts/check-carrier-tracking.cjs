const fs = require('fs');
const assert = require('assert');
const path = require('path');
const frontend = path.resolve(__dirname, '../../sailor-app');
const parser = require(path.join(frontend, 'node_modules/@babel/parser'));
const generate = require(path.join(frontend, 'node_modules/@babel/generator')).default;
const compiler = require(path.join(frontend, 'node_modules/vue-template-compiler/build.js'));

async function check(relative, roles) {
  const source = fs.readFileSync(path.join(frontend, relative), 'utf8');
  const component = compiler.parseComponent(source);
  const compiled = compiler.compile(component.template.content);
  assert.deepStrictEqual(compiled.errors, [], relative + ': template errors');
  const ast = parser.parse(component.script.content, {sourceType: 'module'});
  const exported = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration;
  const methods = exported.properties.find(node => node.key.name === 'methods').value;
  const method = methods.properties.find(node => node.key && node.key.name === 'generarBotonesAcciones');
  const fn = eval('(' + generate(method).code.replace('async generarBotonesAcciones', 'async function generarBotonesAcciones') + ')');
  let cases = 0;
  for (const role of roles) {
    for (const enabled of [0, 1, '1', true, undefined]) {
      for (const legacy of [0, 1, 2, 3, 4]) {
        const context = {
          usuarioActual: {id_role: role},
          dataAux: [{data: [], data_complementaria: {has_carrier_tracking: enabled, has_tracking: legacy, color_indicator: 'text-primary', has_bl: 0}}],
          ActionSetLoadingTable() {},
        };
        await fn.call(context);
        const actions = context.acciones[0];
        assert(actions, `Missing actions for role ${role}`);
        const tracking = actions.filter(action => action.title.startsWith('Ir a Tracking'));
        if (Number(enabled) === 1) {
          assert.deepStrictEqual(tracking.map(action => action.title), ['Ir a Tracking UCC']);
        } else {
          const expected = {1: 'Ir a Tracking', 2: 'Ir a Tracking (1)', 3: 'Ir a Tracking (2)', 4: 'Ir a Tracking UCC'}[legacy];
          assert.deepStrictEqual(tracking.map(action => action.title), expected ? [expected] : []);
        }
        cases++;
      }
    }
  }
  console.log(`${relative}: template/script OK, ${cases} action cases passed`);
}

(async () => {
  await check('src/components/tablas/tracking/TrackingTable.vue', [1, 2, 3, 4, 5, 8, 9999]);
  await check('src/components/tablas/lineas/BookingsEmbarcadosTabla.vue', [1, 2, 3, 4, 5, 8, 9999]);
})().catch(error => { console.error(error); process.exitCode = 1; });
