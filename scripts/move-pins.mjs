// The root `version` script: `npm version <version>` runs it in the release, before
// the release commit. npm writes the `version` of every package.json and updates
// package-lock.json itself; this moves what npm does not know about, to the new
// version: the exact inter-package pins in composer.json and package.json, and the
// version the root composer.json gives the framework path repository. It also moves
// the demo's default runtime image to the previous release's `-db` variant, the
// newest one that exists: the image of this release is built after the tag.
//
// Every pin holds the current version (the `test` job of release.yml refuses a
// release otherwise), so this replaces that exact value and nothing else.
import { execFileSync } from "node:child_process";
import { existsSync, readFileSync, readdirSync, writeFileSync } from "node:fs";

const version = process.env.npm_package_version;
if (!version) throw new Error("Run through `npm version <version>`.");
// npm has already written the new version to package.json: the current one is in
// the last commit.
const current = JSON.parse(
  execFileSync("git", ["show", "HEAD:package.json"], { encoding: "utf8" }),
).version;
const pin = new RegExp(
  `("@?studiometa/foehn(?:-[a-z-]+)?"\\s*:\\s*")${current.replaceAll(".", "\\.")}"`,
  "g",
);

const files = ["composer.json"];
for (const pkg of readdirSync("packages")) {
  for (const name of ["composer.json", "package.json"]) {
    if (existsSync(`packages/${pkg}/${name}`)) files.push(`packages/${pkg}/${name}`);
  }
}
for (const file of files) {
  writeFileSync(file, readFileSync(file, "utf8").replace(pin, `$1${version}"`));
}

const dockerfile = "packages/demo/Dockerfile";
writeFileSync(
  dockerfile,
  readFileSync(dockerfile, "utf8").replace(/(foehn-wordpress:)\S+-db/, `$1${current}-db`),
);
