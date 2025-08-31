const fs = require('fs');

// Third party libs.
const { globSync } = require('glob');
const sass = require('sass');

// Args - allow subtheme generation from this file.
const isSubtheme = process.argv?.[2] === '--subtheme';

// Variables.
const componentDir = './components/';
const fullComponentDir = isSubtheme ? './components_combined/' : componentDir;
const baseOutDir = './dist/';
const cssHeader = '/**\n * This file was automatically generated. Please run `npm run dist` to update.\n */\n\n';
const cssFooter = '\nimg \{\n  display: block;\n  max-width: 100%;\n  height: auto;\n\}\n\n';
const cssContextual = '\nbody.path-xb .contextual \{\n  display: none;\n}\n\n';

// Get Mixin file paths.
const mixins = globSync(`00-base/mixins/**/*.scss`, { cwd: fullComponentDir });

// Get Base style file paths (ignore mixins, reset, variables, and stories).
const base = globSync(`00-base/!(mixins)/!(*.stories|variables|_variables.*|reset).scss`, { cwd: fullComponentDir });

// Generate the base.css file.
const baseData = `
  @import '00-base/reset/reset';
  @import '00-base/variables';
  ${
    [...mixins, ...base].map(path => `@import '${path}';`).join('\n')
  }
  @import 'style.css_variables';
`;
const baseResult = sass.compileString(baseData, { loadPaths: [fullComponentDir] });
fs.writeFileSync(`${baseOutDir}/base.css`, cssHeader + cssContextual + baseResult.css);

// Get component file paths.
const atoms = globSync(`01-atoms/**/*.scss`, { cwd: componentDir });
const molecules = globSync(`02-molecules/**/*.scss`, { cwd: componentDir });
const organisms = globSync(`03-organisms/**/*.scss`, { cwd: componentDir });
const templates = globSync(`04-templates/**/*.scss`, { cwd: componentDir });

const fileList = [
  ...atoms,
  ...molecules,
  ...organisms,
  ...templates,
];

fileList.forEach(filePath => {
  const separator = filePath.lastIndexOf('/') + 1;
  const styleDir = filePath.substring(0, separator);
  const styleName = filePath.substring(separator, filePath.lastIndexOf('.'));

  // Create a sass header to import base mixins.
  const styleData = `
    @import '00-base/variables';
    ${
      mixins.map(path => `@import '${path}';`).join('\n')
    }
    @import '${filePath}';
  `;

  // Compile SCSS.
  const result = sass.compileString(styleData, { loadPaths: [fullComponentDir] });

  // Write to directory.
  fs.writeFileSync(`${componentDir}/${styleDir}/${styleName}.css`, cssHeader + result.css);
});
