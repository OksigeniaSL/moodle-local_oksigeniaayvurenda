# The class island in 3D

The source of `../isla3d.js`, the island of «My class» in 3D (Three.js). Build it with Node:

    npm install
    npm run build

`esbuild` bundles it for Chrome 93, the oldest browser Moodle 5.3 supports. Its target alone leaves the class
static blocks of Three.js (Chrome 94), so the build turns them off by hand; it is tested in a real Chrome 93. The sea shader comes from the island test
that the incubator of Oksigenia made with Claude for Oksigenia. Three.js is under the MIT licence (`LICENSE-three.txt`);
the rest, under the GNU GPL v3 or later, as the plugin.
