#!/bin/sh
# Zabalí každou pluginovou větev do dist/<větev>.zip, připravené k nahrání do WordPressu.
# Spouštět po každé změně pluginu a výsledek commitnout do main.
set -e
cd "$(dirname "$0")"
mkdir -p dist

for branch in velrion-backup velrion-admin velrion-cache velrion-images velrion-images-bulk velrion-snippets velrion-dpd-pickup velrion-shop-manager epoxis-faq epoxis-gravirovani custom-gallery; do
	ref="$branch"
	# V čerstvém klonu existuje větev jen jako origin/<větev>.
	git rev-parse -q --verify "$ref" >/dev/null || ref="origin/$branch"
	git archive --format=zip --prefix="$branch/" -o "dist/$branch.zip" "$ref"
	echo "dist/$branch.zip"
done
