import re
import sys
import pathlib
from pathlib import Path
import datetime
print(pathlib.Path(__file__).parent.parent)

p = Path('.')
for x in p.iterdir():
    print(x)

dirPath = str(pathlib.Path(__file__).parent.parent.parent)

print(dirPath)
for x in pathlib.Path(__file__).parent.parent.parent.iterdir():
    print(x)

name    = dirPath+'/php/_module_menu.php'
path    = Path(name)

# load plugin file
txt = path.read_text()
newVersion  = sys.argv[1]

# get old version
try:
    oldVersion = re.search(r'\* const MODULE_VERSION		= [ \t]*\'([\d.]+)\'', txt).group(1)
except Exception as e:
    exit()

# replace with new
txt = txt.replace(oldVersion, newVersion)

# Write changes
f = open(name, "w")
f.write(txt)
f.close()

# Update the changelog with the new release

file    = 'CHANGELOG.md'

# load plugin file
changelog = Path(file).read_text()

# Get the whole unrelease section
try:
    total       = re.search(r'## \[Unreleased\] - yyyy-mm-dd([\s\S]*?)## \[', changelog).group(1)
    newTotal    = total

    # Remove emty sections
    for x in ["Added", "Changed", "Fixed", "Updated"]:
        pattern = r'(### '+x+'[\s\S]*'

        if(x != 'Updated'):
            pattern = pattern+'?)###'
        else:
            pattern = pattern+')'

        added   = re.search(pattern, total).group(1)

        if(added.rstrip("\n") == '### '+x):
            newTotal    = newTotal.replace(added, '')

    # Update in changelog
    changelog   = changelog.replace(total, newTotal)
except Exception as e:
    pass

# Add new unreleased section
newSection  = "## [Unreleased] - yyyy-mm-dd\n\n### Added\n\n### Changed\n\n### Fixed\n\n### Updated\n\n## [" + newVersion + "] - " + datetime.datetime.now().strftime("%Y-%m-%d")+"\n"
changelog    = changelog.replace('## [Unreleased] - yyyy-mm-dd', newSection)

# Write changes
f = open(file, "w")
f.write(changelog)
f.close()
