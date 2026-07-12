from setuptools import setup, find_packages

with open("requirements.txt") as f:
    install_requires = f.read().strip().split("\n")

setup(
    name="fusionpbx_integration",
    version="1.0.0",
    description="Bidirectional integration between ERPNext/Frappe and FusionPBX",
    author="VOIPEGYPT",
    author_email="info@voipegypt.com",
    packages=find_packages(),
    zip_safe=False,
    include_package_data=True,
    install_requires=install_requires,
)
